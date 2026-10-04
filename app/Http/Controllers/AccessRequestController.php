<?php

namespace App\Http\Controllers;

use App\Models\AccessRequest;
use App\Services\Store;
use Illuminate\Http\Request;

/**
 * Requests for the full text of a Restricted or Partial Access bluebook
 * (Library Manual 4.3.1.4, "Full-Text Access" and "Requests for Multiple
 * Unpublished Materials").
 *
 * Readers can no longer file requests from the System; the library staff
 * still decide, and can revoke, the ones on record. The library decides each request on its own,
 * and approves only on the authorization the author's waiver prescribes - the
 * author's written consent, or a consultation with them - which the approver
 * records. An approval lets that reader view the full text in the watermarked
 * viewer; it never gives them a copy, and it opens no other bluebook.
 */
class AccessRequestController extends Controller
{
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
