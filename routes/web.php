<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AccessRequestController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StudentController;

// ─── Auth ──────────────────────────────────────────────────────────────────────
Route::get('/',          [AuthController::class, 'loginForm'])->name('login');
Route::post('/login',    [AuthController::class, 'login'])->middleware('throttle:login');
// The sign-in page itself is '/', so /login only ever accepted POST and a
// plain visit to it returned a bare "405 Method Not Allowed". Browsers reach
// GET /login constantly - bookmarks, typed URLs, back after a failed submit -
// so send those to the login page instead of an error.
Route::get('/login', fn () => redirect()->route('login'));
Route::get('/forgot-password', [AuthController::class, 'forgotPassword'])->name('password.help');
Route::post('/logout',   [AuthController::class, 'logout'])->name('logout');
// Signing out is a POST now; an old bookmark or typed /logout lands on the
// sign-in page (which sends a signed-in user on to their dashboard) instead
// of a 405, and does not sign anyone out.
Route::get('/logout', fn () => redirect()->route('login'));
Route::post('/session/keep-alive', [AuthController::class, 'keepAlive'])->name('session.keepAlive');

// ─── Public legal pages ────────────────────────────────────────────────────────
// Reachable without signing in: they are linked from the login screen, and a
// visitor has to be able to read them before deciding to authenticate.
Route::view('/terms',   'pages.terms')->name('terms');
Route::view('/privacy', 'pages.privacy')->name('privacy');

// ─── Google OAuth ───────────────────────────────────────────────────────────────
Route::get('/auth/google',          [AuthController::class, 'redirectToGoogle'])->name('auth.google');
Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback'])->name('auth.google.callback');

// ─── Profile (any logged-in role) ──────────────────────────────────────────────
Route::middleware('role:Admin,Sub-Admin,Student,Faculty')->group(function () {
    Route::get('/profile',           [ProfileController::class, 'show'])->name('profile');
    Route::post('/profile',          [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/password', [ProfileController::class, 'password'])->name('profile.password');
});

// ─── Admin ─────────────────────────────────────────────────────────────────────
// Admins and Sub-Admins, who both hold every privilege (see User::allows). The
// permission groups below name what each route needs; only an Admin can manage
// Admin and Sub-Admin accounts (see AdminController::mayManage).
Route::prefix('admin')->middleware('role:Admin,Sub-Admin')->group(function () {
    Route::get('/dashboard',                   [AdminController::class, 'dashboard'])->name('admin.dashboard');

    Route::get('/bluebooks',                   [AdminController::class, 'bluebooks'])->name('admin.bluebooks');
    Route::get('/pending',                     [AdminController::class, 'pendingQueue'])->name('admin.pending');
    Route::get('/rejected',                    [AdminController::class, 'rejectedList'])->name('admin.rejected');
    Route::get('/bluebooks/{id}',              [AdminController::class, 'bluebookView'])->whereNumber('id')->name('admin.bluebooks.view');
    Route::get('/bluebooks/{id}/file',         [AdminController::class, 'bluebookFile'])->whereNumber('id')->name('admin.bluebooks.file');
    Route::get('/bluebooks/{id}/pages/{n}',    [AdminController::class, 'bluebookPage'])->whereNumber(['id', 'n'])->middleware('throttle:bluebook-page')->name('admin.bluebooks.page');

    Route::middleware('permission:manage_bluebooks')->group(function () {
        Route::get('/bluebooks/new',               [AdminController::class, 'bluebookNewForm'])->name('admin.bluebooks.new');
        Route::post('/bluebooks/new',              [AdminController::class, 'bluebookStore'])->name('admin.bluebooks.store');
        Route::post('/bluebooks/{id}/reprocess-ocr', [AdminController::class, 'bluebookReprocessOcr'])->name('admin.bluebooks.reprocessOcr');
        Route::post('/bluebooks/{id}/render-pages', [AdminController::class, 'bluebookRenderPages'])->whereNumber('id')->name('admin.bluebooks.renderPages');
        Route::post('/bluebooks/{id}/delete',       [AdminController::class, 'bluebookDelete'])->name('admin.bluebooks.delete');
    });

    // The edit form is also where a reviewer records the signed waiver; one
    // without manage_bluebooks can change only that (see bluebookUpdate).
    Route::middleware('permission:review_bluebooks,manage_bluebooks')->group(function () {
        Route::get('/bluebooks/{id}/edit',         [AdminController::class, 'bluebookEditForm'])->name('admin.bluebooks.edit');
        Route::post('/bluebooks/{id}/edit',        [AdminController::class, 'bluebookUpdate'])->name('admin.bluebooks.update');
    });

    // Approving is the Admin's alone (User::ADMIN_ONLY).
    Route::middleware('permission:approve_bluebooks')->group(function () {
        Route::post('/bluebooks/{id}/approve',     [AdminController::class, 'bluebookApprove'])->name('admin.bluebooks.approve');
        Route::post('/bluebooks/approve-selected', [AdminController::class, 'bluebookApproveSelected'])->name('admin.bluebooks.approveSelected');
    });

    Route::middleware('permission:review_bluebooks')->group(function () {
        Route::post('/bluebooks/{id}/waiver-received', [AdminController::class, 'bluebookWaiverReceived'])->name('admin.bluebooks.waiverReceived');
        Route::post('/bluebooks/{id}/reject',      [AdminController::class, 'bluebookReject'])->name('admin.bluebooks.reject');
        Route::post('/bluebooks/{id}/evaluate',    [AdminController::class, 'bluebookEvaluate'])->whereNumber('id')->name('admin.bluebooks.evaluate');

        // Readers' requests for the full text of a restricted or partial
        // bluebook, decided one title at a time (Library Manual 4.3.1.4).
        Route::get('/access-requests',               [AccessRequestController::class, 'index'])->name('admin.access-requests');
        Route::post('/access-requests/{id}/approve', [AccessRequestController::class, 'approve'])->whereNumber('id')->name('admin.access-requests.approve');
        Route::post('/access-requests/{id}/deny',    [AccessRequestController::class, 'deny'])->whereNumber('id')->name('admin.access-requests.deny');
        Route::post('/access-requests/{id}/revoke',  [AccessRequestController::class, 'revoke'])->whereNumber('id')->name('admin.access-requests.revoke');
    });

    Route::middleware('permission:manage_users')->group(function () {
        Route::get('/users',                       [AdminController::class, 'users'])->name('admin.users');
        Route::get('/users/new',                   [AdminController::class, 'userNewForm'])->name('admin.users.new');
        Route::post('/users/new',                  [AdminController::class, 'userStore'])->name('admin.users.store');
        Route::get('/users/{id}/edit',             [AdminController::class, 'userEditForm'])->name('admin.users.edit');
        Route::post('/users/{id}/edit',            [AdminController::class, 'userUpdate'])->name('admin.users.update');
        Route::post('/users/{id}/enable-upload',   [AdminController::class, 'enableUpload'])->name('admin.users.enableUpload');
        Route::post('/users/{id}/disable-upload',  [AdminController::class, 'disableUpload'])->name('admin.users.disableUpload');
    });

    Route::get('/logs',                        [AdminController::class, 'logs'])->middleware('permission:view_logs')->name('admin.logs');
});

// ─── Student & Faculty ───────────────────────────────────────────────────────────
Route::prefix('student')->middleware('role:Student,Faculty')->group(function () {
    Route::get('/policy',                      [StudentController::class, 'policy'])->name('student.policy');
    Route::post('/policy/accept',              [StudentController::class, 'acceptPolicy'])->name('student.policy.accept');
    Route::get('/dashboard',                   [StudentController::class, 'dashboard'])->name('student.dashboard');

    Route::get('/bluebooks',                   [StudentController::class, 'bluebooks'])->name('student.bluebooks');
    // The dashboard's search-as-you-type box: the same search as Browse, as JSON.
    Route::get('/bluebooks/search',            [StudentController::class, 'searchBluebooks'])->middleware('throttle:bluebook-search')->name('student.bluebooks.search');
    // Throttled as well as the file route: this page is where a signed link is
    // minted, so a loop over the id range collects one link per document
    // without ever asking for a file.
    Route::get('/bluebooks/{id}',              [StudentController::class, 'bluebookView'])->middleware('throttle:bluebook-read')->name('student.bluebook');
    Route::get('/bluebooks/{id}/file',         [StudentController::class, 'bluebookFile'])->middleware('throttle:bluebook-read')->name('student.bluebook.file');
    Route::get('/bluebooks/{id}/pages/{n}',    [StudentController::class, 'bluebookPage'])->whereNumber(['id', 'n'])->middleware('throttle:bluebook-page')->name('student.bluebook.page');
    Route::post('/bluebooks/{id}/flag-capture', [StudentController::class, 'flagCaptureAttempt'])->middleware('throttle:capture-flag')->name('student.bluebook.flag-capture');

    // Asking the library for the full text of a restricted or partial bluebook.
    Route::post('/bluebooks/{id}/request-access', [AccessRequestController::class, 'store'])->whereNumber('id')->middleware('throttle:6,1')->name('student.bluebook.request-access');
    Route::get('/access-requests',             [AccessRequestController::class, 'mine'])->name('student.access-requests');

    Route::get('/history',                     [StudentController::class, 'history'])->name('student.history');
    Route::get('/bookmarks',                   [StudentController::class, 'bookmarks'])->name('student.bookmarks');

    Route::post('/bookmarks/add/{id}',         [StudentController::class, 'addBookmark'])->name('student.bookmarks.add');
    Route::post('/bookmarks/remove/{id}',      [StudentController::class, 'removeBookmark'])->name('student.bookmarks.remove');
});

// Students only. Faculty browse and read the archive; they do not submit
// papers or use the research tools.
Route::prefix('student')->middleware('role:Student')->group(function () {
    Route::post('/bluebooks/{id}/reprocess-ocr', [StudentController::class, 'reprocessOcr'])->name('student.bluebook.reprocess-ocr');

    Route::get('/upload',                     [StudentController::class, 'uploadForm'])->name('student.upload');
    Route::post('/upload',                     [StudentController::class, 'uploadStore'])->name('student.upload.store');

    Route::get('/similarity-check',             [StudentController::class, 'similarityCheck'])->name('student.similarity-check');
    Route::post('/similarity-check',            [StudentController::class, 'similarityCheck'])->name('student.similarity-check.post');

    Route::get('/literature-review',            [StudentController::class, 'literatureReview'])->name('student.literature-review');
    Route::post('/literature-review',           [StudentController::class, 'literatureReview'])->name('student.literature-review.post');

    Route::get('/my-uploads',                  [StudentController::class, 'myUploads'])->name('student.my-uploads');
    // The blank waiver, to print and fill in at any time - before uploading too.
    Route::get('/waiver',                      [StudentController::class, 'waiverForm'])->name('student.waiver');
    Route::get('/my-uploads/{id}/waiver',     [StudentController::class, 'downloadWaiver'])->name('student.my-uploads.waiver');
    Route::post('/my-uploads/{id}/reupload',   [StudentController::class, 'reupload'])->name('student.my-uploads.reupload');
});
