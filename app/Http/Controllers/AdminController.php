<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessBluebookOcr;
use App\Services\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdminController extends Controller
{
    public function dashboard()
    {
        $user      = session('user');
        $bluebooks = Store::getBluebooks();
        $pending   = array_filter($bluebooks, fn($b) => $b['status'] === 'Pending');
        $approved  = array_filter($bluebooks, fn($b) => $b['status'] === 'Approved');
        $rejected  = array_filter($bluebooks, fn($b) => $b['status'] === 'Rejected');
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
            $fileData = [
                'filePath'         => $file->store('bluebooks', Store::bluebookDisk()),
                'fileOriginalName' => $file->getClientOriginalName(),
                'fileSize'         => $file->getSize(),
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
        ]);
        $user = session('user');

        $fields = [
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
            $fields['filePath']         = $file->store('bluebooks', Store::bluebookDisk());
            $fields['fileOriginalName'] = $file->getClientOriginalName();
            $fields['fileSize']         = $file->getSize();
        }

        Store::updateBluebook($id, $fields);
        Store::addLog(['userName' => $user['name'], 'email' => $user['email'], 'action' => 'Edited Bluebook', 'document' => $request->input('title')]);
        return redirect()->route('admin.bluebooks')->with('success', 'Bluebook updated successfully');
    }

    public function bluebookApprove(int $id)
    {
        $user     = session('user');
        $bluebook = Store::getBluebook($id);
        if ($bluebook) {
            Store::setBluebookStatus($id, 'Approved');
            Store::addLog(['userName' => $user['name'], 'email' => $user['email'], 'action' => 'Approved Bluebook', 'document' => $bluebook['title']]);
        }
        return redirect()->route('admin.bluebooks')->with('success', 'Bluebook approved successfully');
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
        $fields = ['name' => $request->input('name'), 'email' => $request->input('email'), 'role' => $request->input('role')];
        if ($request->input('password')) $fields['password'] = $request->input('password');
        Store::updateUser($id, $fields);
        return redirect()->route('admin.users')->with('success', 'User updated successfully');
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
