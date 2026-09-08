<?php

use Illuminate\Support\Facades\Route;
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
Route::get('/logout',    [AuthController::class, 'logout'])->name('logout');

// ─── Public legal pages ────────────────────────────────────────────────────────
// Reachable without signing in: they are linked from the login screen, and a
// visitor has to be able to read them before deciding to authenticate.
Route::view('/terms',   'pages.terms')->name('terms');
Route::view('/privacy', 'pages.privacy')->name('privacy');

// ─── Google OAuth ───────────────────────────────────────────────────────────────
Route::get('/auth/google',          [AuthController::class, 'redirectToGoogle'])->name('auth.google');
Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback'])->name('auth.google.callback');

// ─── Profile (any logged-in role) ──────────────────────────────────────────────
Route::middleware('role:Admin,Student,Faculty')->group(function () {
    Route::get('/profile',           [ProfileController::class, 'show'])->name('profile');
    Route::post('/profile',          [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/password', [ProfileController::class, 'password'])->name('profile.password');
});

// ─── Admin ─────────────────────────────────────────────────────────────────────
Route::prefix('admin')->middleware('role:Admin')->group(function () {
    Route::get('/dashboard',                   [AdminController::class, 'dashboard'])->name('admin.dashboard');

    Route::get('/bluebooks',                   [AdminController::class, 'bluebooks'])->name('admin.bluebooks');
    Route::get('/bluebooks/new',               [AdminController::class, 'bluebookNewForm'])->name('admin.bluebooks.new');
    Route::post('/bluebooks/new',              [AdminController::class, 'bluebookStore'])->name('admin.bluebooks.store');
    Route::get('/bluebooks/{id}/edit',         [AdminController::class, 'bluebookEditForm'])->name('admin.bluebooks.edit');
    Route::post('/bluebooks/{id}/edit',        [AdminController::class, 'bluebookUpdate'])->name('admin.bluebooks.update');
    Route::post('/bluebooks/{id}/approve',     [AdminController::class, 'bluebookApprove'])->name('admin.bluebooks.approve');
    Route::post('/bluebooks/{id}/reject',      [AdminController::class, 'bluebookReject'])->name('admin.bluebooks.reject');
    Route::post('/bluebooks/{id}/reprocess-ocr', [AdminController::class, 'bluebookReprocessOcr'])->name('admin.bluebooks.reprocessOcr');
    Route::post('/bluebooks/{id}/delete',       [AdminController::class, 'bluebookDelete'])->name('admin.bluebooks.delete');

    Route::get('/users',                       [AdminController::class, 'users'])->name('admin.users');
    Route::get('/users/new',                   [AdminController::class, 'userNewForm'])->name('admin.users.new');
    Route::post('/users/new',                  [AdminController::class, 'userStore'])->name('admin.users.store');
    Route::get('/users/{id}/edit',             [AdminController::class, 'userEditForm'])->name('admin.users.edit');
    Route::post('/users/{id}/edit',            [AdminController::class, 'userUpdate'])->name('admin.users.update');
    Route::post('/users/{id}/enable-upload',   [AdminController::class, 'enableUpload'])->name('admin.users.enableUpload');
    Route::post('/users/{id}/disable-upload',  [AdminController::class, 'disableUpload'])->name('admin.users.disableUpload');

    Route::get('/logs',                        [AdminController::class, 'logs'])->name('admin.logs');
});

// ─── Student ───────────────────────────────────────────────────────────────────
Route::prefix('student')->middleware('role:Student,Faculty')->group(function () {
    Route::get('/policy',                      [StudentController::class, 'policy'])->name('student.policy');
    Route::post('/policy/accept',              [StudentController::class, 'acceptPolicy'])->name('student.policy.accept');
    Route::get('/dashboard',                   [StudentController::class, 'dashboard'])->name('student.dashboard');

    Route::get('/bluebooks',                   [StudentController::class, 'bluebooks'])->name('student.bluebooks');
    Route::get('/bluebooks/{id}',              [StudentController::class, 'bluebookView'])->name('student.bluebook');
    Route::get('/bluebooks/{id}/file',         [StudentController::class, 'bluebookFile'])->name('student.bluebook.file');
    Route::post('/bluebooks/{id}/flag-capture', [StudentController::class, 'flagCaptureAttempt'])->name('student.bluebook.flag-capture');
    Route::post('/bluebooks/{id}/reprocess-ocr', [StudentController::class, 'reprocessOcr'])->name('student.bluebook.reprocess-ocr');

    Route::get('/upload',                      [StudentController::class, 'uploadForm'])->name('student.upload');
    Route::post('/upload',                     [StudentController::class, 'uploadStore'])->name('student.upload.store');

    Route::get('/similarity-check',             [StudentController::class, 'similarityCheck'])->name('student.similarity-check');
    Route::post('/similarity-check',            [StudentController::class, 'similarityCheck'])->name('student.similarity-check.post');

    Route::get('/literature-review',            [StudentController::class, 'literatureReview'])->name('student.literature-review');
    Route::post('/literature-review',           [StudentController::class, 'literatureReview'])->middleware('throttle:literature-review-ai')->name('student.literature-review.post');

    Route::get('/my-uploads',                  [StudentController::class, 'myUploads'])->name('student.my-uploads');
    Route::get('/history',                     [StudentController::class, 'history'])->name('student.history');
    Route::get('/bookmarks',                   [StudentController::class, 'bookmarks'])->name('student.bookmarks');

    Route::post('/bookmarks/add/{id}',         [StudentController::class, 'addBookmark'])->name('student.bookmarks.add');
    Route::post('/bookmarks/remove/{id}',      [StudentController::class, 'removeBookmark'])->name('student.bookmarks.remove');
});
