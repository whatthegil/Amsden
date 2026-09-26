<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessBluebookOcr;
use App\Models\Bluebook;
use App\Models\User;
use App\Services\SearchService;
use App\Services\SimilarityService;
use App\Services\Store;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
    use Concerns\ReadsAccessWaiver;
    use Concerns\StreamsBluebookDocument;

    private const ROLES = ['Student', 'Faculty', User::ROLE_SUB_ADMIN, User::ROLE_ADMIN];

    /**
     * The roles the signed-in user may hand out. Only an Admin creates staff; a
     * Sub-Admin with manage_users looks after student and faculty accounts.
     */
    private function assignableRoles(): array
    {
        return (session('user')['role'] ?? null) === User::ROLE_ADMIN ? self::ROLES : ['Student', 'Faculty'];
    }

    /** Whether the signed-in user may change this account at all. */
    private function mayManage(array $target): bool
    {
        return (session('user')['role'] ?? null) === User::ROLE_ADMIN || !User::isStaff($target['role']);
    }

    /** The privileges ticked on the form, kept only for a Sub-Admin and only from an Admin. */
    private function permissionsFrom(Request $request, string $role): ?array
    {
        if ($role !== User::ROLE_SUB_ADMIN || (session('user')['role'] ?? null) !== User::ROLE_ADMIN) {
            return null;
        }

        return array_values(array_intersect(array_keys(User::PERMISSIONS), (array) $request->input('permissions', [])));
    }

    public function dashboard()
    {
        $user      = session('user');
        $bluebooks = Store::getBluebooks();
        $pending   = array_filter($bluebooks, fn($b) => $b['status'] === 'Pending');
        $approved  = array_filter($bluebooks, fn($b) => $b['status'] === 'Approved');
        $rejected  = array_filter($bluebooks, fn($b) => $b['status'] === 'Rejected');
        $awaiting  = array_filter($bluebooks, fn($b) => $b['status'] === Bluebook::STATUS_AWAITING_WAIVER);
        $recent    = array_reverse($bluebooks);

        return view('pages.admin-dashboard', [
            'user'         => $user,
            'active'       => 'dashboard',
            'pendingCount' => count($pending),
            'stats'        => [
                'totalBluebooks'  => count($bluebooks),
                'approved'        => count($approved),
                'pending'         => count($pending),
                'rejected'        => count($rejected),
                'awaitingWaiver'  => count($awaiting),
                'totalUsers'      => count(Store::getUsers()),
                'recentLogs'      => Store::getRecentLogs(5),
                'recentBluebooks' => array_slice($recent, 0, 5),
            ],
        ]);
    }

    public function bluebooks(Request $request)
    {
        $q = $request->only(['search', 'department', 'year', 'status']);

        // The archive: papers that are posted, or approved and waiting only for
        // the signed waiver. Pending and Rejected have pages of their own.
        $archive = ['Approved', Bluebook::STATUS_AWAITING_WAIVER];
        $status  = in_array($q['status'] ?? null, $archive, true) ? $q['status'] : null;
        $books   = array_values(array_filter(
            Store::getBluebooks($q['search'] ?? null, $q['department'] ?? null, isset($q['year']) && $q['year'] ? (int)$q['year'] : null, $status),
            fn($b) => in_array($b['status'], $archive, true)
        ));

        return view('pages.admin-bluebooks', [
            'user'          => session('user'),
            'active'        => 'bluebooks',
            'bluebooks'     => $books,
            'years'        => Store::getYears(),
            'query'        => $q,
            'pendingCount' => Store::getPendingCount(),
            'success'      => session('success'),
        ]);
    }

    public function bluebookNewForm()
    {
        return view('pages.admin-bluebook-form', ['user' => session('user'), 'active' => 'bluebooks', 'bluebook' => null, 'pendingCount' => Store::getPendingCount()]);
    }

    public function bluebookStore(Request $request)
    {
        $request->validate([
            'file' => ['nullable', 'file', 'mimes:pdf', 'max:35840', new \App\Rules\PdfFile], // 35MB, PDF only
        ]);
        $user = session('user');

        $fileData = [];
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $path = $file->store('bluebooks', Store::bluebookDisk());

            $stamped = Store::stampStoredBluebook($path, [
                'title' => $request->input('title'),
                'year'  => (int) $request->input('year'),
            ]);

            $fileData = [
                'filePath'         => $path,
                'fileOriginalName' => $file->getClientOriginalName(),
                'fileSize'         => $file->getSize(),
                'watermarkedAt'    => $stamped ? now() : null,
            ];
        }

        Store::addBluebook(array_merge([
            'title'          => $request->input('title'),
            'authors'        => array_map('trim', explode(';', $request->input('authors'))),
            'year'           => (int)$request->input('year'),
            'department'     => $request->input('department'),
            'program'        => $request->input('program'),
            'keywords'       => array_map('trim', explode(',', $request->input('keywords'))),
            'abstract'       => $request->input('abstract'),
            'adviser'        => $request->input('adviser'),
            'status'         => 'Approved',
            // Added by the library itself, so posted as public with no waiver step.
            'waiverRecordedAt' => now(),
            'uploadedBy'     => $user['email'],
            'uploadedByName' => $user['name'],
            'pages'          => (int)$request->input('pages'),
        ], $fileData));
        Store::addLog(['userName' => $user['name'], 'email' => $user['email'], 'action' => 'Added Bluebook', 'document' => $request->input('title')]);
        return redirect()->route('admin.bluebooks')->with('success', 'Bluebook added successfully');
    }

    /**
     * The admin reads a bluebook at any stage - a pending upload has to be read
     * before it can be approved - and reads all of it: the waiver decides what
     * readers are sent, not what the library can check. Not counted as a view.
     */
    public function bluebookView(int $id)
    {
        $user     = session('user');
        $bluebook = Store::getBluebook($id);
        if (!$bluebook) return redirect()->route('admin.bluebooks');

        Store::addLog(['userName' => $user['name'], 'email' => $user['email'], 'action' => 'Viewed Bluebook', 'document' => $bluebook['title']]);

        return view('pages.student-bluebook-view', [
            'user'         => $user,
            'active'       => 'bluebooks',
            'bluebook'     => $bluebook,
            'isBookmarked' => false,
            'fileUrl'      => null,
            'asAdmin'      => true,
            'pendingCount' => Store::getPendingCount(),
        ]);
    }

    /** The whole document, at any status, stamped with the admin's email. */
    public function bluebookFile(int $id)
    {
        $bluebook = Store::getBluebook($id);
        if (!$bluebook || !$bluebook['hasFile']) {
            abort(404);
        }

        $disk = Storage::disk(Store::bluebookDisk());
        if (!$disk->exists($bluebook['filePath'])) {
            abort(404);
        }

        return $this->streamBluebookDocument($bluebook, session('user'), $disk, null);
    }

    public function bluebookEditForm(int $id)
    {
        $bluebook = Store::getBluebook($id);
        if (!$bluebook) return redirect()->route('admin.bluebooks');
        return view('pages.admin-bluebook-form', ['user' => session('user'), 'active' => 'bluebooks', 'bluebook' => $bluebook, 'pendingCount' => Store::getPendingCount()]);
    }

    public function bluebookUpdate(Request $request, int $id)
    {
        // A reviewer without manage_bluebooks comes here to record the signed
        // waiver, and may change that and nothing else - the page ranges are
        // checked against the stored page count, not one sent with the form.
        $canManage = User::allows(session('user'), 'manage_bluebooks');
        if (!$canManage) {
            $existing = Store::getBluebook($id);
            if (!$existing) return redirect()->route('admin.bluebooks');
            $request->merge(['pages' => $existing['pages']]);
        }

        $request->validate([
            'file' => ['nullable', 'file', 'mimes:pdf', 'max:35840', new \App\Rules\PdfFile], // 35MB, PDF only
        ] + $this->accessWaiverRules(), $this->accessWaiverMessages());
        $accessParts = $this->accessPartsFrom($request);
        $user = session('user');

        $fields = [
            'accessLevel' => $request->input('access_level'),
            'accessParts' => $accessParts,
            'title'      => $request->input('title'),
            'authors'    => array_map('trim', explode(';', $request->input('authors'))),
            'year'       => (int)$request->input('year'),
            'department' => $request->input('department'),
            'program'    => $request->input('program'),
            'keywords'   => array_map('trim', explode(',', $request->input('keywords'))),
            'abstract'   => $request->input('abstract'),
            'adviser'    => $request->input('adviser'),
            'pages'      => (int)$request->input('pages'),
        ];

        if (!$canManage) {
            $fields = array_intersect_key($fields, ['accessLevel' => 1, 'accessParts' => 1]);
        }

        if ($canManage && $request->hasFile('file')) {
            $existing = Store::getBluebook($id);
            if ($existing && $existing['filePath']) {
                Storage::disk(Store::bluebookDisk())->delete($existing['filePath']);
            }
            $file = $request->file('file');
            $path = $file->store('bluebooks', Store::bluebookDisk());

            // A replacement is a new document as far as the mark is concerned.
            $stamped = Store::stampStoredBluebook($path, [
                'title' => $request->input('title'),
                'year'  => (int) $request->input('year'),
            ]);

            $fields['watermarkedAt']    = $stamped ? now() : null;
            $fields['filePath']         = $path;
            $fields['fileOriginalName'] = $file->getClientOriginalName();
            $fields['fileSize']         = $file->getSize();
        }

        Store::updateBluebook($id, $fields);
        Store::addLog(['userName' => $user['name'], 'email' => $user['email'], 'action' => $canManage ? 'Edited Bluebook' : 'Recorded Waiver', 'document' => Store::getBluebook($id)['title'] ?? $request->input('title')]);
        return redirect()->route('admin.bluebooks')->with('success', 'Bluebook updated successfully');
    }

    public function bluebookApprove(Request $request, int $id)
    {
        $user     = session('user');
        $bluebook = Store::getBluebook($id);
        if (!$bluebook || $bluebook['status'] !== 'Pending') {
            return redirect($this->reviewedFrom($request));
        }
        // Approval is not posting: the author must first hand in the printed,
        // signed waiver, which the admin records with waiverReceived().
        Store::setBluebookStatus($id, Bluebook::STATUS_AWAITING_WAIVER);
        Store::addLog(['userName' => $user['name'], 'email' => $user['email'], 'action' => 'Approved Bluebook', 'document' => $bluebook['title']]);
        return redirect($this->reviewedFrom($request))->with('success', 'Bluebook approved. It will be posted once the author hands in the signed waiver.');
    }

    /** Back to the page the action was taken from: Pending, Rejected, or the list. */
    private function reviewedFrom(Request $request): string
    {
        return match ($request->input('from')) {
            'pending'  => route('admin.pending'),
            'rejected' => route('admin.rejected'),
            default    => route('admin.bluebooks'),
        };
    }

    /** A submission waiting longer than this is flagged as overdue. */
    private const OVERDUE_DAYS = 7;

    /** A rejected author silent longer than this is flagged for a follow-up. */
    private const NO_REPLY_DAYS = 14;

    /**
     * Submissions waiting for review. Each is checked against the posted
     * archive for a likely duplicate, and the queue can be searched, narrowed
     * to a department and put longest- or shortest-waiting first.
     */
    public function pendingQueue(Request $request)
    {
        $q     = $request->only(['search', 'department', 'sort']);
        $all   = Store::getBluebooks(null, null, null, 'Pending');
        $books = $this->queueFilter($all, $q);

        $newest = ($q['sort'] ?? '') === 'newest';
        usort($books, fn($a, $b) => $newest
            ? strcmp((string) $b['updatedAt'], (string) $a['updatedAt'])
            : strcmp((string) $a['updatedAt'], (string) $b['updatedAt']));

        // A capstone that repeats one already in the archive is the thing a
        // reviewer most needs to catch, and the hardest to spot by eye.
        $approved = Store::getApprovedBluebooks();
        foreach ($books as &$book) {
            $book['waitingDays'] = $this->daysSince($book['updatedAt']);
            $book['duplicate']   = null;
            foreach ($approved as $posted) {
                $score = SimilarityService::computeSimilarity($book['title'], $book['keywords'], (string) $book['abstract'], $posted);
                if ($score >= 0.45 && ($book['duplicate'] === null || $score > $book['duplicate']['score'])) {
                    $book['duplicate'] = ['id' => $posted['id'], 'title' => $posted['title'], 'score' => $score];
                }
            }
        }
        unset($book);

        $ages = array_map(fn($b) => $this->daysSince($b['updatedAt']), $all);

        return view('pages.admin-bluebook-queue', [
            'user'         => session('user'),
            'active'       => 'pending',
            'mode'         => 'pending',
            'bluebooks'    => $books,
            'query'        => $q,
            'summary'      => [
                'total'   => count($all),
                'oldest'  => $ages ? max($ages) : 0,
                'overdue' => count(array_filter($ages, fn($d) => $d > self::OVERDUE_DAYS)),
            ],
            'overdueDays'  => self::OVERDUE_DAYS,
            'pendingCount' => count($all),
        ]);
    }

    /** Rejected submissions and why, the most recent first, flagging authors who have not re-uploaded. */
    public function rejectedList(Request $request)
    {
        $q     = $request->only(['search', 'department']);
        $all   = Store::getBluebooks(null, null, null, 'Rejected');
        $books = $this->queueFilter($all, $q);
        usort($books, fn($a, $b) => strcmp((string) $b['updatedAt'], (string) $a['updatedAt']));

        foreach ($books as &$book) {
            $book['waitingDays'] = $this->daysSince($book['updatedAt']);
        }
        unset($book);

        $ages = array_map(fn($b) => $this->daysSince($b['updatedAt']), $all);

        return view('pages.admin-bluebook-queue', [
            'user'         => session('user'),
            'active'       => 'rejected',
            'mode'         => 'rejected',
            'bluebooks'    => $books,
            'query'        => $q,
            'summary'      => [
                'total'   => count($all),
                'noReply' => count(array_filter($ages, fn($d) => $d > self::NO_REPLY_DAYS)),
            ],
            'noReplyDays'  => self::NO_REPLY_DAYS,
            'pendingCount' => Store::getPendingCount(),
        ]);
    }

    /** Narrow a queue by a search over title, authors and uploader, and by department. */
    private function queueFilter(array $books, array $q): array
    {
        $needle = SearchService::normalize($q['search'] ?? '');
        $dept   = $q['department'] ?? '';

        return array_values(array_filter($books, function ($b) use ($needle, $dept) {
            if ($dept !== '' && $b['department'] !== $dept) return false;
            if ($needle === '') return true;

            $hay = SearchService::normalize($b['title'] . ' ' . implode(' ', $b['authors']) . ' ' . $b['uploadedByName'] . ' ' . $b['uploadedBy']);
            foreach (explode(' ', $needle) as $word) {
                if (!str_contains($hay, $word)) return false;
            }
            return true;
        }));
    }

    private function daysSince(?string $at): int
    {
        return $at ? max(0, (int) floor((time() - strtotime($at)) / 86400)) : 0;
    }

    /** Approve several pending submissions at once, from the queue. */
    public function bluebookApproveSelected(Request $request)
    {
        $user     = session('user');
        $approved = 0;

        foreach (array_unique(array_map('intval', (array) $request->input('ids', []))) as $id) {
            $bluebook = Store::getBluebook($id);
            if (!$bluebook || $bluebook['status'] !== 'Pending') continue;

            Store::setBluebookStatus($id, Bluebook::STATUS_AWAITING_WAIVER);
            Store::addLog(['userName' => $user['name'], 'email' => $user['email'], 'action' => 'Approved Bluebook', 'document' => $bluebook['title']]);
            $approved++;
        }

        return redirect()->route('admin.pending')->with('success', $approved === 0
            ? 'Tick at least one pending submission to approve.'
            : $approved . ' ' . Str::plural('submission', $approved) . ' approved. Each will be posted once its author hands in the signed waiver.');
    }

    public function bluebookWaiverReceived(int $id)
    {
        $user     = session('user');
        $bluebook = Store::getBluebook($id);
        if (!$bluebook || $bluebook['status'] !== Bluebook::STATUS_AWAITING_WAIVER) {
            return redirect()->route('admin.bluebooks');
        }
        // Posting without the author's chosen level would publish it as
        // public by default, whatever the signed waiver says.
        if (!$bluebook['waiverRecorded']) {
            return redirect()->route('admin.bluebooks.edit', $id)
                ->withErrors(['access_level' => 'Set the access level from the signed waiver before posting this bluebook.']);
        }
        Store::setBluebookStatus($id, 'Approved');
        Store::addLog(['userName' => $user['name'], 'email' => $user['email'], 'action' => 'Received Waiver', 'document' => $bluebook['title']]);
        return redirect()->route('admin.bluebooks')->with('success', 'Waiver received. The bluebook is now posted in Browse.');
    }

    public function bluebookReject(Request $request, int $id)
    {
        $user     = session('user');
        $bluebook = Store::getBluebook($id);
        if (!$bluebook) {
            return redirect()->route('admin.bluebooks');
        }

        // The author sees this in My Uploads; it is how they know what to fix
        // before re-uploading, so a rejection without one is not accepted.
        $reason = trim((string) $request->input('reason'));
        if ($reason === '') {
            return redirect($this->reviewedFrom($request))->with('success', 'Please give a reason for rejecting "' . $bluebook['title'] . '".');
        }
        $reason = mb_substr($reason, 0, 1000);

        Store::updateBluebook($id, ['status' => 'Rejected', 'rejectionReason' => $reason]);
        Store::addLog(['userName' => $user['name'], 'email' => $user['email'], 'action' => 'Rejected Bluebook', 'document' => $bluebook['title']]);
        return redirect($this->reviewedFrom($request))->with('success', 'Bluebook rejected. The author can see your reason and re-upload.');
    }

    public function bluebookDelete(Request $request, int $id)
    {
        $user     = session('user');
        $bluebook = Store::deleteBluebook($id);

        if (!$bluebook) {
            return redirect($this->reviewedFrom($request))->with('error', 'That bluebook no longer exists.');
        }

        Store::addLog([
            'userName' => $user['name'],
            'email'    => $user['email'],
            'action'   => 'Deleted Bluebook',
            'document' => $bluebook['title'],
        ]);

        return redirect($this->reviewedFrom($request))->with('success', 'Bluebook deleted: ' . $bluebook['title']);
    }

    public function bluebookReprocessOcr(int $id)
    {
        $user     = session('user');
        $bluebook = Store::getBluebook($id);

        if (!$bluebook || !$bluebook['hasFile']) {
            return redirect()->route('admin.bluebooks')->with('success', 'Bluebook has no file to process');
        }
        // Only for posted bluebooks - before that the file may still be
        // replaced (a re-upload after rejection runs OCR on its own).
        if ($bluebook['status'] !== 'Approved') {
            return redirect()->route('admin.bluebooks')->with('success', 'OCR can be reprocessed once the bluebook is posted');
        }
        if ($bluebook['ocrStatus'] === 'processing' && !$bluebook['ocrStuck']) {
            return redirect()->route('admin.bluebooks')->with('success', 'OCR is already processing for this bluebook');
        }

        ProcessBluebookOcr::dispatch($id);
        Store::addLog(['userName' => $user['name'], 'email' => $user['email'], 'action' => 'Reprocessed OCR', 'document' => $bluebook['title']]);

        return redirect()->route('admin.bluebooks')->with('success', 'OCR reprocessing started');
    }

    public function users(Request $request)
    {
        $q = $request->only(['search', 'role']);
        return view('pages.admin-users', [
            'user'         => session('user'),
            'active'       => 'users',
            'users'        => Store::getUsers($q['search'] ?? null, $q['role'] ?? null),
            'query'        => $q,
            'pendingCount' => Store::getPendingCount(),
            'success'      => session('success'),
        ]);
    }

    public function userNewForm()
    {
        return view('pages.admin-user-form', ['user' => session('user'), 'active' => 'users', 'editUser' => null, 'roles' => $this->assignableRoles(), 'pendingCount' => Store::getPendingCount()]);
    }

    public function userStore(Request $request)
    {
        $user = session('user');
        if (Store::findUserByEmail($request->input('email'))) {
            return redirect()->route('admin.users')->with('success', 'Email already exists');
        }
        if (!in_array($request->input('role'), $this->assignableRoles(), true)) {
            return redirect()->route('admin.users')->with('success', 'Invalid role');
        }
        Store::addUser(['name' => $request->input('name'), 'email' => $request->input('email'), 'password' => $request->input('password'), 'role' => $request->input('role'), 'permissions' => $this->permissionsFrom($request, $request->input('role'))]);
        Store::addLog(['userName' => $user['name'], 'email' => $user['email'], 'action' => 'Added User', 'document' => $request->input('email')]);
        return redirect()->route('admin.users')->with('success', 'User added successfully');
    }

    public function userEditForm(int $id)
    {
        $editUser = null;
        foreach (Store::getUsers() as $u) {
            if ($u['id'] === $id) { $editUser = $u; break; }
        }
        if (!$editUser) return redirect()->route('admin.users');
        if (!$this->mayManage($editUser)) {
            return redirect()->route('admin.users')->with('success', 'Only an Admin can change an Admin or Sub-Admin account');
        }
        return view('pages.admin-user-form', ['user' => session('user'), 'active' => 'users', 'editUser' => $editUser, 'roles' => $this->assignableRoles(), 'pendingCount' => Store::getPendingCount()]);
    }

    public function userUpdate(Request $request, int $id)
    {
        $user   = session('user');
        $target = $this->findUser($id);
        if (!$target) return redirect()->route('admin.users');
        if (!$this->mayManage($target)) {
            return redirect()->route('admin.users')->with('success', 'Only an Admin can change an Admin or Sub-Admin account');
        }

        $role = $request->input('role');
        if (!in_array($role, $this->assignableRoles(), true)) {
            return redirect()->route('admin.users')->with('success', 'Invalid role');
        }
        // An admin taking away their own Admin role would lock themselves out
        // of this panel mid-session, so that has to be done by another admin.
        if ($target['role'] === 'Admin' && $role !== 'Admin' && $id === (int) $user['id']) {
            return redirect()->route('admin.users')->with('success', 'You cannot remove your own Admin role');
        }

        $fields = ['name' => $request->input('name'), 'email' => $request->input('email'), 'role' => $role, 'permissions' => $this->permissionsFrom($request, $role)];
        if ($request->input('password')) $fields['password'] = $request->input('password');
        Store::updateUser($id, $fields);
        if ($target['role'] !== $role) {
            Store::addLog(['userName' => $user['name'], 'email' => $user['email'], 'action' => "Changed Role to $role", 'document' => $target['name']]);
        }
        return redirect()->route('admin.users')->with('success', 'User updated successfully');
    }

    private function findUser(int $id): ?array
    {
        foreach (Store::getUsers() as $u) {
            if ($u['id'] === $id) return $u;
        }
        return null;
    }

    public function enableUpload(int $id)
    {
        $user = session('user');
        $target = null;
        foreach (Store::getUsers() as $u) { if ($u['id'] === $id) { $target = $u; break; } }
        if (!$target || !$this->mayManage($target)) {
            return redirect()->route('admin.users')->with('success', 'Only an Admin can change an Admin or Sub-Admin account');
        }
        Store::setUploadPermission($id, true);
        Store::addLog(['userName' => $user['name'], 'email' => $user['email'], 'action' => 'Enabled Upload Permission', 'document' => $target ? $target['name'] : '—']);
        return redirect()->route('admin.users')->with('success', 'Upload permission enabled');
    }

    public function disableUpload(int $id)
    {
        $user = session('user');
        $target = null;
        foreach (Store::getUsers() as $u) { if ($u['id'] === $id) { $target = $u; break; } }
        if (!$target || !$this->mayManage($target)) {
            return redirect()->route('admin.users')->with('success', 'Only an Admin can change an Admin or Sub-Admin account');
        }
        Store::setUploadPermission($id, false);
        Store::addLog(['userName' => $user['name'], 'email' => $user['email'], 'action' => 'Disabled Upload Permission', 'document' => $target ? $target['name'] : '—']);
        return redirect()->route('admin.users')->with('success', 'Upload permission disabled');
    }

    public function logs(Request $request)
    {
        $q = $request->only(['search', 'action']);

        // The log only grows; printing all of it made one page of every entry
        // ever recorded. Newest first, a page at a time.
        $all     = Store::getLogs($q['search'] ?? null, $q['action'] ?? null);
        $perPage = 50;
        $total   = count($all);
        $pages   = max(1, (int) ceil($total / $perPage));
        $page    = min($pages, max(1, (int) $request->query('page', 1)));
        $offset  = ($page - 1) * $perPage;

        return view('pages.admin-logs', [
            'user'         => session('user'),
            'active'       => 'logs',
            'logs'         => array_slice($all, $offset, $perPage),
            'total'        => $total,
            'page'         => $page,
            'pages'        => $pages,
            'offset'       => $offset,
            'actions'      => Store::getLogActions(),
            'query'        => $q,
            'pendingCount' => Store::getPendingCount(),
        ]);
    }
}
