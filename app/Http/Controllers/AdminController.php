<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessBluebookOcr;
use App\Models\Bluebook;
use App\Services\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
    private const ROLES = ['Student', 'Faculty', 'Admin'];

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

        return view('pages.admin-bluebooks', [
            'user'         => session('user'),
            'active'       => 'bluebooks',
            'bluebooks'    => Store::getBluebooks($q['search'] ?? null, $q['department'] ?? null, isset($q['year']) && $q['year'] ? (int)$q['year'] : null, $q['status'] ?? null),
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
            'uploadedBy'     => $user['email'],
            'uploadedByName' => $user['name'],
            'pages'          => (int)$request->input('pages'),
        ], $fileData));
        Store::addLog(['userName' => $user['name'], 'email' => $user['email'], 'action' => 'Added Bluebook', 'document' => $request->input('title')]);
        return redirect()->route('admin.bluebooks')->with('success', 'Bluebook added successfully');
    }

    public function bluebookEditForm(int $id)
    {
        $bluebook = Store::getBluebook($id);
        if (!$bluebook) return redirect()->route('admin.bluebooks');
        return view('pages.admin-bluebook-form', ['user' => session('user'), 'active' => 'bluebooks', 'bluebook' => $bluebook, 'pendingCount' => Store::getPendingCount()]);
    }

    public function bluebookUpdate(Request $request, int $id)
    {
        $request->validate([
            'file' => ['nullable', 'file', 'mimes:pdf', 'max:35840', new \App\Rules\PdfFile], // 35MB, PDF only
            'access_level'   => ['required', 'in:' . implode(',', array_keys(Bluebook::ACCESS_LEVELS))],
            'access_parts'   => ['required_if:access_level,' . Bluebook::ACCESS_PARTIAL, 'array'],
            'access_parts.*' => ['in:' . implode(',', array_keys(Bluebook::ACCESS_PARTS))],
        ], [
            'access_level.required'    => 'Please choose an access permission waiver.',
            'access_parts.required_if' => 'Please tick at least one part that readers may see.',
        ]);
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

        if ($request->hasFile('file')) {
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
        Store::addLog(['userName' => $user['name'], 'email' => $user['email'], 'action' => 'Edited Bluebook', 'document' => $request->input('title')]);
        return redirect()->route('admin.bluebooks')->with('success', 'Bluebook updated successfully');
    }

    /**
     * The parts a partial waiver opens, each with the pages it occupies.
     *
     * The page range is what the waiver is enforced by - the served copy holds
     * those pages and no others - so a part ticked without a usable range is an
     * error rather than something to guess at.
     *
     * @return array<string, array{from: int, to: int}>|null
     */
    private function accessPartsFrom(Request $request): ?array
    {
        if ($request->input('access_level') !== Bluebook::ACCESS_PARTIAL) {
            return null;
        }

        $pages  = (int) $request->input('pages');
        $from   = (array) $request->input('part_from', []);
        $to     = (array) $request->input('part_to', []);
        $parts  = [];

        foreach (array_keys(Bluebook::ACCESS_PARTS) as $key) {
            if (!in_array($key, (array) $request->input('access_parts', []), true)) {
                continue;
            }

            $label = Bluebook::ACCESS_PARTS[$key];
            $start = filter_var($from[$key] ?? null, FILTER_VALIDATE_INT);
            $end   = filter_var($to[$key] ?? null, FILTER_VALIDATE_INT);

            if ($start === false || $end === false) {
                throw ValidationException::withMessages([
                    'access_parts' => "Please enter the page range for {$label}.",
                ]);
            }
            if ($start < 1 || $end < $start || $end > $pages) {
                throw ValidationException::withMessages([
                    'access_parts' => "The page range for {$label} must run forward and fall within pages 1 to {$pages}.",
                ]);
            }

            $parts[$key] = ['from' => $start, 'to' => $end];
        }

        return $parts;
    }

    public function bluebookApprove(int $id)
    {
        $user     = session('user');
        $bluebook = Store::getBluebook($id);
        if (!$bluebook || $bluebook['status'] !== 'Pending') {
            return redirect()->route('admin.bluebooks');
        }
        // Approval is not posting: the author must first hand in the printed,
        // signed waiver, which the admin records with waiverReceived().
        Store::setBluebookStatus($id, Bluebook::STATUS_AWAITING_WAIVER);
        Store::addLog(['userName' => $user['name'], 'email' => $user['email'], 'action' => 'Approved Bluebook', 'document' => $bluebook['title']]);
        return redirect()->route('admin.bluebooks')->with('success', 'Bluebook approved. It will be posted once the author hands in the signed waiver.');
    }

    public function bluebookWaiverReceived(int $id)
    {
        $user     = session('user');
        $bluebook = Store::getBluebook($id);
        if (!$bluebook || $bluebook['status'] !== Bluebook::STATUS_AWAITING_WAIVER) {
            return redirect()->route('admin.bluebooks');
        }
        Store::setBluebookStatus($id, 'Approved');
        Store::addLog(['userName' => $user['name'], 'email' => $user['email'], 'action' => 'Received Waiver', 'document' => $bluebook['title']]);
        return redirect()->route('admin.bluebooks')->with('success', 'Waiver received. The bluebook is now posted in Browse.');
    }

    public function bluebookReject(int $id)
    {
        $user     = session('user');
        $bluebook = Store::getBluebook($id);
        if ($bluebook) {
            Store::setBluebookStatus($id, 'Rejected');
            Store::addLog(['userName' => $user['name'], 'email' => $user['email'], 'action' => 'Rejected Bluebook', 'document' => $bluebook['title']]);
        }
        return redirect()->route('admin.bluebooks')->with('success', 'Bluebook rejected');
    }

    public function bluebookDelete(int $id)
    {
        $user     = session('user');
        $bluebook = Store::deleteBluebook($id);

        if (!$bluebook) {
            return redirect()->route('admin.bluebooks')->with('error', 'That bluebook no longer exists.');
        }

        Store::addLog([
            'userName' => $user['name'],
            'email'    => $user['email'],
            'action'   => 'Deleted Bluebook',
            'document' => $bluebook['title'],
        ]);

        return redirect()->route('admin.bluebooks')->with('success', 'Bluebook deleted: ' . $bluebook['title']);
    }

    public function bluebookReprocessOcr(int $id)
    {
        $user     = session('user');
        $bluebook = Store::getBluebook($id);

        if (!$bluebook || !$bluebook['hasFile']) {
            return redirect()->route('admin.bluebooks')->with('success', 'Bluebook has no file to process');
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
        return view('pages.admin-user-form', ['user' => session('user'), 'active' => 'users', 'editUser' => null, 'pendingCount' => Store::getPendingCount()]);
    }

    public function userStore(Request $request)
    {
        $user = session('user');
        if (Store::findUserByEmail($request->input('email'))) {
            return redirect()->route('admin.users')->with('success', 'Email already exists');
        }
        if (!in_array($request->input('role'), self::ROLES, true)) {
            return redirect()->route('admin.users')->with('success', 'Invalid role');
        }
        Store::addUser(['name' => $request->input('name'), 'email' => $request->input('email'), 'password' => $request->input('password'), 'role' => $request->input('role')]);
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
        return view('pages.admin-user-form', ['user' => session('user'), 'active' => 'users', 'editUser' => $editUser, 'pendingCount' => Store::getPendingCount()]);
    }

    public function userUpdate(Request $request, int $id)
    {
        $user   = session('user');
        $target = $this->findUser($id);
        if (!$target) return redirect()->route('admin.users');

        $role = $request->input('role');
        if (!in_array($role, self::ROLES, true)) {
            return redirect()->route('admin.users')->with('success', 'Invalid role');
        }
        // An admin taking away their own Admin role would lock themselves out
        // of this panel mid-session, so that has to be done by another admin.
        if ($target['role'] === 'Admin' && $role !== 'Admin' && $id === (int) $user['id']) {
            return redirect()->route('admin.users')->with('success', 'You cannot remove your own Admin role');
        }

        $fields = ['name' => $request->input('name'), 'email' => $request->input('email'), 'role' => $role];
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
        Store::setUploadPermission($id, true);
        Store::addLog(['userName' => $user['name'], 'email' => $user['email'], 'action' => 'Enabled Upload Permission', 'document' => $target ? $target['name'] : '—']);
        return redirect()->route('admin.users')->with('success', 'Upload permission enabled');
    }

    public function disableUpload(int $id)
    {
        $user = session('user');
        $target = null;
        foreach (Store::getUsers() as $u) { if ($u['id'] === $id) { $target = $u; break; } }
        Store::setUploadPermission($id, false);
        Store::addLog(['userName' => $user['name'], 'email' => $user['email'], 'action' => 'Disabled Upload Permission', 'document' => $target ? $target['name'] : '—']);
        return redirect()->route('admin.users')->with('success', 'Upload permission disabled');
    }

    public function logs(Request $request)
    {
        $q = $request->only(['search', 'action']);
        return view('pages.admin-logs', [
            'user'         => session('user'),
            'active'       => 'logs',
            'logs'         => Store::getLogs($q['search'] ?? null, $q['action'] ?? null),
            'actions'      => Store::getLogActions(),
            'query'        => $q,
            'pendingCount' => Store::getPendingCount(),
        ]);
    }
}
