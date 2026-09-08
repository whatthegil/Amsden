<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessBluebookOcr;
use App\Services\LiteratureReviewService;
use App\Services\OcrService;
use App\Services\PdfTextExtractor;
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

    public function acceptPolicy(Request $request)
    {
        // Each document is agreed to separately, and both are checked here
        // rather than relying on the markup: acceptance is recorded once,
        // permanently, and is the only record that this account holder was
        // shown these terms, so a request that skips a box must not count.
        $request->validate(
            [
                'agree_terms'   => ['accepted'],
                'agree_privacy' => ['accepted'],
            ],
            [
                'agree_terms.accepted'   => 'Please confirm you have read and agree to the Terms and Conditions.',
                'agree_privacy.accepted' => 'Please confirm you have read and agree to the Privacy Policy.',
            ]
        );

        $user = session('user');
        Store::acceptPolicy($user['email']);
        Store::addLog([
            'userName' => $user['name'],
            'email'    => $user['email'],
            'action'   => 'Accepted Policy',
            'document' => 'Acceptable Use, Terms and Conditions, Privacy Policy',
        ]);

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
        $disk = Storage::disk(Store::bluebookDisk());

        if (!$disk->exists($bluebook['filePath'])) {
            abort(404);
        }

        // The document is streamed rather than handed to Storage::response().
        // That helper sets Content-Length from the object's recorded size and
        // then streams the body separately; when the two disagree nginx aborts
        // the response mid-flight with
        //
        //     upstream sent more data than specified in "Content-Length" header
        //
        // and the browser gets a 503 instead of a PDF. Letting the response go
        // out chunked, with no declared length, removes the disagreement.
        $stream = $disk->readStream($bluebook['filePath']);

        if ($stream === false) {
            abort(404);
        }

        return response()->stream(function () use ($stream) {
            // Flushed in chunks so the first bytes reach the viewer promptly on
            // a document that runs to tens of megabytes, rather than the whole
            // file being buffered before anything is sent.
            while (!feof($stream)) {
                echo fread($stream, 262144);
                flush();
            }
            fclose($stream);
        }, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . ($bluebook['fileOriginalName'] ?? 'document.pdf') . '"',
            'Cache-Control'       => 'no-store',
            'X-Frame-Options'     => 'SAMEORIGIN',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function flagCaptureAttempt(Request $request, int $id)
    {
        $user     = session('user');
        $bluebook = Store::getBluebook($id);

        // Which signal fired matters when reviewing these later: a PrintScreen
        // press or a snipping shortcut is a deliberate capture attempt, while a
        // lost focus or hidden tab is often just someone switching windows. The
        // value comes from the page, so it is constrained to a known set rather
        // than written into the log as-is.
        $allowed = [
            'PrintScreen key',
            'Snipping shortcut (Win+Shift+S)',
            'macOS screenshot shortcut',
            'Window lost focus',
            'Tab hidden',
            'Screen sharing',
            'Watermark tampering',
        ];
        $reason = (string) $request->input('reason', '');
        $reason = in_array($reason, $allowed, true) ? $reason : 'Unknown';

        Store::addLog([
            'userName' => $user['name'],
            'email'    => $user['email'],
            'action'   => 'Screenshot/Recording Attempt',
            'document' => ($bluebook ? $bluebook['title'] : '—') . ' — ' . $reason,
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

        if ($bluebook['ocrStatus'] === 'processing' && !$bluebook['ocrStuck']) {
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
                'file'       => ['required', 'file', 'mimes:pdf', 'max:35840', new \App\Rules\PdfFile], // 35MB, PDF only
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
            $path  = $file->store('bluebooks', Store::bluebookDisk());

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
        $error    = null;

        if ($request->isMethod('post')) {
            $mode = $request->hasFile('file') ? 'file' : 'text';

            if ($mode === 'file') {
                [$proposed, $error] = $this->similarityProposalFromFile($request);
            } else {
                $proposed = [
                    'title'    => trim($request->input('title', '')),
                    'keywords' => array_values(array_filter(array_map('trim', explode(',', $request->input('keywords', ''))))),
                    'abstract' => trim($request->input('abstract', '')),
                ];
                if ($proposed['title'] === '') {
                    $error = 'Please enter a proposed research title.';
                }
            }

            if ($error === null && $proposed !== null) {
                $results = $this->runSimilarityCheck($proposed, $mode);

                Store::addLog([
                    'userName' => $user['name'],
                    'email'    => $user['email'],
                    'action'   => $mode === 'file' ? 'Similarity Check (pre-proposal upload)' : 'Similarity Check',
                    'document' => $proposed['title'] ?: ($proposed['sourceFile'] ?? '—'),
                ]);
            }
        }

        return view('pages.student-similarity-check', [
            'user'     => $user,
            'active'   => 'similarity',
            'results'  => $results,
            'proposed' => $proposed,
            'error'    => $error,
        ]);
    }

    /**
     * Score a proposed capstone against every approved bluebook and keep the
     * matches above the display threshold, highest first. `$mode` picks the
     * scorer: 'text' compares the typed title/keywords/abstract fields;
     * 'file' compares the whole extracted pre-proposal text.
     */
    private function runSimilarityCheck(array $proposed, string $mode): array
    {
        $results = [];

        foreach (Store::getApprovedBluebooks() as $book) {
            $score = $mode === 'file'
                ? SimilarityService::computeSimilarityFromText($proposed['proposalText'] ?? '', $book)
                : SimilarityService::computeSimilarity(
                    $proposed['title'], $proposed['keywords'], $proposed['abstract'], $book
                );

            $threshold = $mode === 'file' ? 0.12 : 0.08;
            if ($score >= $threshold) {
                $results[] = [
                    'bluebook'   => $book,
                    'score'      => $score,
                    'percentage' => round($score * 100, 1),
                ];
            }
        }

        usort($results, fn($a, $b) => $b['score'] <=> $a['score']);

        return $results;
    }

    /**
     * Validate the uploaded pre-proposal PDF, pull its text out (embedded text
     * layer first, a short OCR pass as fallback for scanned documents), and
     * shape it into the same $proposed array the typed path produces — plus
     * `proposalText` (for scoring) and `sourceFile` (for display).
     *
     * @return array{0: array|null, 1: string|null} [$proposed, $error]
     */
    private function similarityProposalFromFile(Request $request): array
    {
        try {
            $request->validate([
                'file' => ['required', 'file', 'mimes:pdf', 'max:35840', new \App\Rules\PdfFile],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return [null, collect($e->errors())->flatten()->first()];
        }

        $file       = $request->file('file');
        $sourceName = $file->getClientOriginalName();

        $tmpDir  = storage_path('app/similarity-tmp');
        if (!is_dir($tmpDir)) {
            @mkdir($tmpDir, 0777, true);
        }
        $tmpName = uniqid('proposal_', true) . '.pdf';
        $tmpPath = $tmpDir . DIRECTORY_SEPARATOR . $tmpName;

        try {
            $file->move($tmpDir, $tmpName);

            $text = PdfTextExtractor::extract($tmpPath);

            // Scanned / image-only PDF — no text layer. Try a short OCR pass
            // (capped hard so the request still returns promptly).
            if (mb_strlen($text) < 150) {
                try {
                    $pages = (int) config('ocr.sync_max_pages', 12);
                    // Even capped, OCR runs ~3s/page, which overruns php-fpm's default
                    // 30s max_execution_time. Lift the ceiling for this request only;
                    // the page cap is what actually bounds the work.
                    if (function_exists('set_time_limit')) {
                        @set_time_limit((int) config('ocr.timeout') + ($pages * 15));
                    }
                    $text = OcrService::extractText($tmpPath, $pages)['text'];
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        } catch (\Throwable $e) {
            report($e);
            @unlink($tmpPath);
            return [null, 'Something went wrong while reading that PDF. Please try again.'];
        } finally {
            @unlink($tmpPath);
        }

        $text = trim($text);
        if (mb_strlen($text) < 150) {
            return [null, 'We couldn\'t read any text from that PDF. It may be a scanned image without a selectable text layer — try typing your title above, or upload a text-based PDF.'];
        }

        return [[
            'title'        => $this->guessProposalTitle($text, $sourceName),
            'keywords'     => [],
            'abstract'     => '',
            'proposalText' => $text,
            'sourceFile'   => $sourceName,
        ], null];
    }

    /**
     * Best-effort title for display: an explicit "Title: ..." line if the
     * document has one, otherwise the first substantial line of text, falling
     * back to the file name.
     */
    private function guessProposalTitle(string $text, string $sourceName): string
    {
        $lines = preg_split('/\n+/', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($lines as $line) {
            if (preg_match('/^\s*(?:proposed\s+)?(?:research\s+)?title\s*[:\-]\s*(.+)$/i', trim($line), $m)) {
                $candidate = trim($m[1]);
                if (mb_strlen($candidate) >= 10) {
                    return mb_substr($candidate, 0, 300);
                }
            }
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if (mb_strlen($line) >= 15 && mb_strlen($line) <= 250 && str_word_count($line) >= 3) {
                return mb_substr($line, 0, 300);
            }
        }

        return pathinfo($sourceName, PATHINFO_FILENAME) ?: 'Uploaded pre-proposal';
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
