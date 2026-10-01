<?php

namespace App\Http\Controllers;

use App\Models\AccessRequest;
use App\Models\Bluebook;
use App\Services\Store;
use Illuminate\Http\Request;

/**
 * Requests for the full text of a Restricted or Partial Access bluebook
 * (Library Manual 4.3.1.4, "Full-Text Access" and "Requests for Multiple
 * Unpublished Materials").
 *
 * A reader asks for one bluebook at a time, giving their research title,
 * program, adviser and purpose. The library decides each request on its own,
 * and approves only on the authorization the author's waiver prescribes - the
 * author's written consent, or a consultation with them - which the approver
 * records. An approval lets that reader view the full text in the watermarked
 * viewer; it never gives them a copy, and it opens no other bluebook.
 */
class AccessRequestController extends Controller
{
    // ── Readers ──────────────────────────────────────────────────────────────

    public function store(Request $request, int $id)
    {
        $user     = session('user');
        $bluebook = Store::getBluebook($id);
        if (!$bluebook || $bluebook['status'] !== 'Approved') {
            return redirect()->route('student.bluebooks');
        }

        if (!in_array($bluebook['accessLevel'], Bluebook::REQUESTABLE_LEVELS, true)) {
            return redirect()->route('student.bluebook', $id)
                ->with('error', 'This bluebook does not need a request: everything readers may see is already shown.');
        }

        if (AccessRequest::current($id, $user['email'])) {
            return redirect()->route('student.bluebook', $id)
                ->with('error', 'You have already requested access to this bluebook.');
        }

        $data = $request->validate([
            'research_title' => ['required', 'string', 'max:500'],
            'program'        => ['required', 'string', 'max:255'],
            'adviser'        => ['required', 'string', 'max:255'],
            'purpose'        => ['required', 'string', 'max:2000'],
            'agree'          => ['accepted'],
        ], [
            'agree.accepted' => 'Please confirm that you will use the material only for academic purposes and will not reproduce or share it.',
        ]);

        AccessRequest::create([
            'bluebook_id'    => $id,
            'user_email'     => $user['email'],
            'user_name'      => $user['name'],
            'research_title' => trim($data['research_title']),
            'program'        => trim($data['program']),
            'adviser'        => trim($data['adviser']),
            'purpose'        => trim($data['purpose']),
            'status'         => AccessRequest::STATUS_PENDING,
        ]);
        Store::addLog(['userName' => $user['name'], 'email' => $user['email'], 'action' => 'Requested Access', 'document' => $bluebook['title'], 'bluebookId' => $id]);

        return redirect()->route('student.bluebook', $id)
            ->with('success', 'Your request has been sent to the library. You will see its decision here and under Access Requests.');
    }

    public function mine()
    {
        $user = session('user');

        return view('pages.student-access-requests', [
            'user'     => $user,
            'active'   => 'access-requests',
            'requests' => AccessRequest::with('bluebook:id,title,access_level')
                ->where('user_email', $user['email'])
                ->latest('id')
                ->get(),
        ]);
    }

    // ── Library staff ────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $status = $request->query('status', AccessRequest::STATUS_PENDING);
        $valid  = [AccessRequest::STATUS_PENDING, AccessRequest::STATUS_APPROVED, AccessRequest::STATUS_DENIED, AccessRequest::STATUS_REVOKED, 'all'];
        if (!in_array($status, $valid, true)) {
            $status = AccessRequest::STATUS_PENDING;
        }

        $query = AccessRequest::with('bluebook:id,title,access_level,access_parts,authors')
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [AccessRequest::STATUS_PENDING])
            ->latest('id');
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        return view('pages.admin-access-requests', [
            'user'         => session('user'),
            'active'       => 'access-requests',
            'requests'     => $query->paginate(25)->withQueryString(),
            'status'       => $status,
            'counts'       => AccessRequest::selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status'),
            'pendingCount' => Store::getPendingCount(),
        ]);
    }

    public function approve(Request $request, int $id)
    {
        $data = $request->validate([
            'authorization' => ['required', 'string', 'max:1000'],
        ], [
            'authorization.required' => 'Record the authorization this approval rests on - the author\'s written consent or a consultation with them.',
        ]);

        return $this->decide($id, AccessRequest::STATUS_PENDING, AccessRequest::STATUS_APPROVED, $data['authorization'], 'Approved Access Request', 'Access approved. The reader can now view the full text, less any withheld pages.');
    }

    public function deny(Request $request, int $id)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ], [
            'reason.required' => 'Give the reason for denying the request; the reader sees it.',
        ]);

        return $this->decide($id, AccessRequest::STATUS_PENDING, AccessRequest::STATUS_DENIED, $data['reason'], 'Denied Access Request', 'Request denied. The reader can see your reason.');
    }

    public function revoke(Request $request, int $id)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ], [
            'reason.required' => 'Give the reason for revoking access.',
        ]);

        return $this->decide($id, AccessRequest::STATUS_APPROVED, AccessRequest::STATUS_REVOKED, $data['reason'], 'Revoked Access', 'Access revoked.');
    }

    private function decide(int $id, string $from, string $to, string $note, string $action, string $message)
    {
        $user = session('user');
        $req  = AccessRequest::with('bluebook:id,title')->find($id);
        if (!$req || $req->status !== $from) {
            return redirect()->route('admin.access-requests')->with('error', 'That request has already been decided.');
        }

        $req->update([
            'status'        => $to,
            'decision_note' => mb_substr(trim($note), 0, 1000),
            'decided_by'    => $user['email'],
            'decided_at'    => now(),
        ]);
        Store::addLog([
            'userName'   => $user['name'],
            'email'      => $user['email'],
            'action'     => $action,
            'document'   => ($req->bluebook->title ?? 'Bluebook') . ' — for ' . $req->user_email,
            'bluebookId' => $req->bluebook_id,
        ]);

        return redirect()->back()->with('success', $message);
    }
}
