<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessBluebookOcr;
use App\Services\LiteratureReviewService;
use App\Services\SimilarityService;
use App\Services\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentController extends Controller
{
    public function policy()
    {
        return view('pages.policy', ['user' => session('user')]);
    }

    public function acceptPolicy()
    {
        $user = session('user');
        Store::acceptPolicy($user['email']);
        Store::addLog(['userName' => $user['name'], 'email' => $user['email'], 'action' => 'Accepted Policy', 'document' => '—']);
        return redirect()->route('student.dashboard');
    }

    public function dashboard()
    {
        $user     = session('user');
        $approved = Store::getApprovedBluebooks();
        $my       = array_values(array_filter(Store::getBluebooks(), fn($b) => $b['uploadedBy'] === $user['email']));

        $recent = $approved;
        usort($recent, fn($a, $b) => strtotime($b['dateAdded']) - strtotime($a['dateAdded']));
        $recent = array_slice($recent, 0, 4);

        $popular = $approved;
        usort($popular, fn($a, $b) => $b['views'] - $a['views']);
        $popular = array_slice($popular, 0, 4);

        return view('pages.student-dashboard', [
            'user'             => $user,
            'active'           => 'dashboard',
            'recentBluebooks'  => $recent,
            'popularBluebooks' => $popular,
            'myBluebooks'      => $my,
            'totalApproved'    => count($approved),
        ]);
    }

    public function bluebooks(Request $request)
    {
        $user = session('user');
        $q    = $request->only(['search', 'department', 'year']);

        return view('pages.student-bluebooks', [
            'user'      => $user,
            'active'    => 'bluebooks',
            'bluebooks' => Store::getApprovedBluebooks($q['search'] ?? null, $q['department'] ?? null, isset($q['year']) && $q['year'] ? (int)$q['year'] : null),
            'years'     => Store::getYears(),
            'query'     => $q,
        ]);
    }

    public function bluebookView(int $id)
    {
        $user     = session('user');
        $bluebook = Store::getBluebook($id);
        if (!$bluebook || $bluebook['status'] !== 'Approved') return redirect()->route('student.bluebooks');

        Store::incrementViews($id);
        Store::addLog(['userName' => $user['name'], 'email' => $user['email'], 'action' => 'Viewed Bluebook', 'document' => $bluebook['title']]);

        return view('pages.student-bluebook-view', [
            'user'         => $user,
            'active'       => 'bluebooks',
            'bluebook'     => Store::getBluebook($id),
            'isBookmarked' => Store::isBookmarked($user['email'], $id),
        ]);
    }

    public function bluebookFile(int $id)
    {
        $user     = session('user');
        $bluebook = Store::getBluebook($id);
        if (!$bluebook || $bluebook['status'] !== 'Approved' || !$bluebook['hasFile']) {
            abort(404);
        }
        if (!Storage::disk('local')->exists($bluebook['filePath'])) {
            abort(404);
        }

        return Storage::disk('local')->response(
            $bluebook['filePath'],
            $bluebook['fileOriginalName'] ?? 'document.pdf',
            [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . ($bluebook['fileOriginalName'] ?? 'document.pdf') . '"',
                'Cache-Control'       => 'no-store',
                'X-Frame-Options'     => 'SAMEORIGIN',
            ]
        );
    }

    public function flagCaptureAttempt(Request $request, int $id)
    {
        $user     = session('user');
        $bluebook = Store::getBluebook($id);

        Store::addLog([
            'userName' => $user['name'],
            'email'    => $user['email'],
            'action'   => 'Screenshot/Recording Attempt',
            'document' => $bluebook ? $bluebook['title'] : '—',
            'status'   => 'Flagged',
        ]);

        return response()->json(['ok' => true]);
    }

    public function reprocessOcr(int $id)
    {
        $user     = session('user');
        $bluebook = Store::getBluebook($id);

        if (!$bluebook || !$bluebook['hasFile']) {
            abort(404);
        }

        // Ownership check: role middleware only confirms the caller is a
        // Student/Faculty, not that this is *their* bluebook — without this,
        // any Student/Faculty could reprocess OCR on any other user's upload.
        if ($bluebook['uploadedBy'] !== $user['email']) {
            Store::addLog([
                'userName' => $user['name'],
                'email'    => $user['email'],
                'action'   => 'Unauthorized Access Attempt',
                'document' => $bluebook['title'],
                'status'   => 'Denied',
            ]);
            abort(403);
        }

        if ($bluebook['ocrStatus'] === 'processing') {
            return redirect()->route('student.my-uploads')->with('success', 'OCR is already processing for this bluebook');
        }

        ProcessBluebookOcr::dispatch($id);
        Store::addLog(['userName' => $user['name'], 'email' => $user['email'], 'action' => 'Reprocessed OCR', 'document' => $bluebook['title']]);

        return redirect()->route('student.my-uploads')->with('success', 'OCR reprocessing started');
    }

    public function uploadForm()
    {
        $user = session('user');
        if (!($user['canUpload'] ?? false)) {
            return view('pages.student-upload-denied', ['user' => $user, 'active' => 'upload']);
        }
        return view('pages.student-upload', ['user' => $user, 'active' => 'upload', 'error' => null, 'success' => null, 'old' => []]);
    }

    public function uploadStore(Request $request)
    {
        $user = session('user');
        if (!($user['canUpload'] ?? false)) {
            return view('pages.student-upload-denied', ['user' => $user, 'active' => 'upload']);
        }

        try {
            $request->validate([
                'file'       => ['required', 'file', 'mimes:pdf', 'max:25600'], // 25MB
                'title'      => ['required', 'string'],
                'authors'    => ['required', 'string'],
                'department' => ['required', 'string'],
                'program'    => ['required', 'string'],
                'year'       => ['required', 'integer'],
                'adviser'    => ['required', 'string'],
                'pages'      => ['required', 'integer', 'min:1'],
                'keywords'   => ['required', 'string'],
                'abstract'   => ['required', 'string'],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return view('pages.student-upload', [
                'user'    => $user,
                'active'  => 'upload',
                'error'   => collect($e->errors())->flatten()->first(),
                'success' => null,
                'old'     => $request->except('file', '_token'),
            ]);
        }

        try {
            $title = $request->input('title');
            $file  = $request->file('file');
            $path  = $file->store('bluebooks', 'local');

            $bluebook = Store::addBluebook([
                'title'          => $title,
                'authors'        => array_map('trim', explode(';', $request->input('authors'))),
                'year'           => (int)$request->input('year'),
                'department'     => $request->input('department'),
                'program'        => $request->input('program'),
                'keywords'       => array_map('trim', explode(',', $request->input('keywords'))),
                'abstract'       => $request->input('abstract'),
                'adviser'        => $request->input('adviser'),
                'status'         => 'Pending',
                'uploadedBy'     => $user['email'],
                'uploadedByName' => $user['name'],
                'pages'          => (int)$request->input('pages'),
                'filePath'         => $path,
                'fileOriginalName' => $file->getClientOriginalName(),
                'fileSize'         => $file->getSize(),
            ]);
            Store::addLog(['userName' => $user['name'], 'email' => $user['email'], 'action' => 'Uploaded Bluebook', 'document' => $title]);
            ProcessBluebookOcr::dispatch($bluebook['id']);
        } catch (\Throwable $e) {
            report($e);
            return view('pages.student-upload', [
                'user'    => $user,
                'active'  => 'upload',
                'error'   => 'Something went wrong while uploading your bluebook. Please try again.',
                'success' => null,
                'old'     => $request->except('file', '_token'),
            ]);
        }

        return view('pages.student-upload', [
            'user'    => $user,
            'active'  => 'upload',
            'error'   => null,
            'success' => 'Bluebook uploaded successfully! It is now pending admin approval.',
            'old'     => [],
        ]);
    }

    public function myUploads()
    {
        $user = session('user');
        $my   = array_values(array_filter(Store::getBluebooks(), fn($b) => $b['uploadedBy'] === $user['email']));
        return view('pages.student-my-uploads', ['user' => $user, 'active' => 'my-uploads', 'bluebooks' => $my]);
    }

    public function history()
    {
        $user = session('user');
        return view('pages.student-history', ['user' => $user, 'active' => 'history', 'logs' => Store::getLogsByEmail($user['email'])]);
    }

    public function bookmarks()
    {
        $user = session('user');
        return view('pages.student-bookmarks', ['user' => $user, 'active' => 'bookmarks', 'bluebooks' => Store::getBookmarkedBluebooks($user['email'])]);
    }

    public function addBookmark(int $id)
    {
        $user     = session('user');
        $bluebook = Store::getBluebook($id);
        Store::addBookmark($user['email'], $user['name'], $id);
        Store::addLog(['userName' => $user['name'], 'email' => $user['email'], 'action' => 'Bookmarked Bluebook', 'document' => $bluebook ? $bluebook['title'] : '—']);
        return redirect()->route('student.bookmarks');
    }

    public function similarityCheck(Request $request)
    {
        $user = session('user');

        $results  = null;
        $proposed = null;

        if ($request->isMethod('post')) {
            $proposedTitle    = trim($request->input('title', ''));
            $proposedKeywords = array_filter(array_map('trim', explode(',', $request->input('keywords', ''))));
            $proposedAbstract = trim($request->input('abstract', ''));

            $proposed = [
                'title'    => $proposedTitle,
                'keywords' => array_values($proposedKeywords),
                'abstract' => $proposedAbstract,
            ];

            $bluebooks = Store::getApprovedBluebooks();
            $results   = [];

            foreach ($bluebooks as $book) {
                $score = SimilarityService::computeSimilarity(
                    $proposedTitle, array_values($proposedKeywords), $proposedAbstract, $book
                );
                if ($score >= 0.08) {
                    $results[] = [
                        'bluebook'   => $book,
                        'score'      => $score,
                        'percentage' => round($score * 100, 1),
                    ];
                }
            }

            usort($results, fn($a, $b) => $b['score'] <=> $a['score']);

            Store::addLog([
                'userName' => $user['name'],
                'email'    => $user['email'],
                'action'   => 'Similarity Check',
                'document' => $proposedTitle,
            ]);
        }

        return view('pages.student-similarity-check', [
            'user'     => $user,
            'active'   => 'similarity',
            'results'  => $results,
            'proposed' => $proposed,
        ]);
    }

    public function literatureReview(Request $request)
    {
        $user = session('user');

        $results = null;
        $topic   = null;

        if ($request->isMethod('post')) {
            $topic   = trim($request->input('topic', ''));
            $results = $topic !== ''
                ? LiteratureReviewService::search($topic, Store::getApprovedBluebooks())
                : [];

            if ($topic !== '') {
                Store::addLog([
                    'userName' => $user['name'],
                    'email'    => $user['email'],
                    'action'   => 'Literature Review Search',
                    'document' => $topic,
                ]);
            }
        }

        return view('pages.student-literature-review', [
            'user'    => $user,
            'active'  => 'literature-review',
            'results' => $results,
            'topic'   => $topic,
        ]);
    }

    public function removeBookmark(Request $request, int $id)
    {
        $user = session('user');
        Store::removeBookmark($user['email'], $id);
        $from = $request->query('from', 'bookmarks');
        return $from === 'view'
            ? redirect()->route('student.bluebook', ['id' => $id])
            : redirect()->route('student.bookmarks');
    }
}
