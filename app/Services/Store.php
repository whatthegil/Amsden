<?php

namespace App\Services;

use App\Models\Bluebook;
use App\Models\Bookmark;
use App\Models\Log;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class Store
{
    /**
     * Disk holding uploaded bluebook PDFs.
     *
     * Single source of truth for every read, write and delete of a bluebook
     * file. This used to be the string 'local' repeated across the upload,
     * replace, stream and OCR paths, which meant the archive could not be
     * moved to durable storage without finding all of them.
     */
    public static function bluebookDisk(): string
    {
        return config('filesystems.bluebooks', 'local');
    }

    /**
     * A URL the browser can fetch a document from directly, or null.
     *
     * Every view used to pull the whole object from storage through PHP and out
     * again - tens of megabytes per read, logged at one point as 'executing too
     * slow (7.46 sec)' with a Guzzle stack underneath it. Worse for the reader,
     * that response has to go out chunked with no Content-Length (declaring one
     * is what nginx rejected in d96a734), and with no length there is nothing
     * for PDF.js to range-request against: it must hold the entire file before
     * it can draw page one.
     *
     * A signed URL hands the fetch to the bucket, which answers ranges, so the
     * first page arrives after a few kilobytes rather than the whole document.
     *
     * Null when the disk cannot sign one - the local driver in development -
     * and the caller falls back to streaming through PHP.
     */
    public static function bluebookFileUrl(string $path, ?int $minutes = null): ?string
    {
        // Off unless asked for, because of what the link is: for its lifetime
        // it is a bearer token. Anyone holding it reads the document with no
        // session, no role check, and no watermark - the copy it returns is the
        // stored PDF, not what the viewer draws. Handing that to a reader who
        // can pass it on is a wider opening than the session route, where the
        // same bytes at least require being logged in as someone.
        //
        // The speed is real and so is the cost, so this is a setting rather
        // than a decision taken here. BLUEBOOK_DIRECT_FETCH=true turns it on.
        if (!config('filesystems.bluebook_direct_fetch', false)) {
            return null;
        }

        $disk = Storage::disk(self::bluebookDisk());

        if (!$disk->providesTemporaryUrls()) {
            return null;
        }

        // The link has to outlive the read, not the request: PDF.js keeps
        // fetching ranges for as long as the document is open, so a link that
        // expires mid-read leaves the reader on a page that will not draw.
        $minutes ??= (int) config('filesystems.bluebook_link_ttl', 60);

        try {
            return $disk->temporaryUrl($path, now()->addMinutes($minutes), [
                // The archive is read-only by design and the wrapper app has no
                // PDF viewer of its own, so the object must never arrive as a
                // download, whatever the bucket has recorded against it.
                'ResponseContentType'        => 'application/pdf',
                'ResponseContentDisposition' => 'inline',
            ]);
        } catch (\Throwable $e) {
            // Misconfigured credentials or an endpoint that cannot sign. The
            // stream through PHP still works, so this degrades rather than
            // fails - but it is the slow path, so it should be noticed.
            report($e);
            return null;
        }
    }

    /**
     * Write the archive's own mark into a freshly stored document.
     *
     * Done once, here, rather than at each of the three places a file can be
     * uploaded from - the student form, the admin create, and the admin
     * replace - so a fourth cannot quietly arrive unstamped.
     *
     * Best effort on purpose: a document that could not be stamped is still a
     * document, and refusing the upload over a missing binary would lose the
     * archive to protect it. The failure is logged by the stamper.
     */
    public static function stampStoredBluebook(string $path, array $meta): bool
    {
        if (!config('watermark.stored', true)) {
            return false;
        }

        [$line1, $line2] = \App\Services\Pdf\PdfWatermarker::provenanceLines($meta);

        return \App\Services\Pdf\PdfWatermarker::stampInPlace($path, $line1, $line2);
    }

    public static function now(): string
    {
        return now()->setTimezone('Asia/Manila')->format('Y-m-d H:i:s');
    }

    // ─── Users ────────────────────────────────────────────────────────────────

    public static function getUsers(?string $search = null, ?string $role = null): array
    {
        $query = User::query();
        if ($search) {
            $query->where(fn($q) =>
                $q->where('name', 'LIKE', "%$search%")
                  ->orWhere('email', 'LIKE', "%$search%")
            );
        }
        if ($role) $query->where('role', $role);
        return $query->get()->map(fn($u) => self::userToArray($u))->toArray();
    }

    public static function findUserByEmail(string $email): ?array
    {
        $user = User::whereRaw('LOWER(email) = ?', [strtolower(trim($email))])->first();
        return $user ? self::userToArray($user) : null;
    }

    public static function findUserForLogin(string $email, string $password): ?array
    {
        $user = User::where('email', $email)->first();
        if (!$user || !Hash::check($password, $user->password)) return null;
        return self::userToArray($user);
    }

    public static function addUser(array $userData): array
    {
        $user = User::create([
            'name'       => $userData['name'],
            'email'      => strtolower(trim($userData['email'])),
            'password'   => Hash::make($userData['password']),
            'role'       => $userData['role'] ?? 'Student',
            'can_upload' => $userData['canUpload'] ?? false,
        ]);
        return self::userToArray($user);
    }

    public static function updateUser(int $id, array $fields): void
    {
        $user = User::find($id);
        if (!$user) return;
        if (isset($fields['name']))      $user->name       = $fields['name'];
        if (isset($fields['email']))     $user->email      = strtolower(trim($fields['email']));
        if (isset($fields['password']))  $user->password   = Hash::make($fields['password']);
        if (isset($fields['role']))      $user->role       = $fields['role'];
        if (isset($fields['canUpload'])) $user->can_upload = $fields['canUpload'];
        $user->save();
    }

    public static function setUploadPermission(int $id, bool $canUpload): void
    {
        User::where('id', $id)->update(['can_upload' => $canUpload]);
    }

    public static function acceptPolicy(string $email): void
    {
        User::where('email', $email)->whereNull('policy_accepted_at')->update(['policy_accepted_at' => self::now()]);
    }

    // ─── Bluebooks ────────────────────────────────────────────────────────────

    public static function getBluebooks(?string $search = null, ?string $department = null, ?int $year = null, ?string $status = null): array
    {
        $query = Bluebook::query();
        if ($search) {
            // Match on the whole phrase OR any individual word, so e.g.
            // "management system" also finds documents where those words
            // appear separately. This is a broad recall filter only — actual
            // ranking happens below via SimilarityService::searchRelevance().
            $terms = array_unique(array_merge([$search], SimilarityService::tokenize($search)));
            $query->where(function ($sq) use ($terms) {
                foreach ($terms as $term) {
                    $like = '%' . $term . '%';
                    $sq->orWhere('title',    'LIKE', $like)
                       ->orWhere('abstract', 'LIKE', $like)
                       ->orWhere('authors',  'LIKE', $like)
                       ->orWhere('keywords', 'LIKE', $like)
                       ->orWhere('ocr_text', 'LIKE', $like);
                }
            });
        }
        if ($department) $query->where('department', $department);
        if ($year)       $query->where('year', $year);
        if ($status)     $query->where('status', $status);
        $results = $query->latest()->get()->map(fn($b) => self::bookToArray($b))->toArray();

        // Rank by relevance to the query instead of upload recency, so an
        // exact title/keyword match surfaces above a barely-matching but
        // more recently uploaded bluebook.
        if ($search) {
            $scores = [];
            foreach ($results as $b) {
                $scores[$b['id']] = SimilarityService::searchRelevance($search, $b);
            }
            usort($results, fn($a, $b) => $scores[$b['id']] <=> $scores[$a['id']]);
        }

        return $results;
    }

    public static function getApprovedBluebooks(?string $search = null, ?string $department = null, ?int $year = null): array
    {
        return self::getBluebooks($search, $department, $year, 'Approved');
    }

    public static function getBluebook(int $id): ?array
    {
        $b = Bluebook::find($id);
        return $b ? self::bookToArray($b) : null;
    }

    public static function addBluebook(array $bookData): array
    {
        $b = Bluebook::create([
            'title'            => $bookData['title'],
            'authors'          => $bookData['authors'],
            'year'             => $bookData['year'],
            'department'       => $bookData['department'],
            'program'          => $bookData['program'],
            'keywords'         => $bookData['keywords'],
            'abstract'         => $bookData['abstract'],
            'adviser'          => $bookData['adviser'],
            'status'           => $bookData['status'] ?? 'Pending',
            'uploaded_by'      => $bookData['uploadedBy'],
            'uploaded_by_name' => $bookData['uploadedByName'],
            'pages'            => $bookData['pages'] ?? 0,
            'views'            => 0,
            'date_added'       => $bookData['dateAdded'] ?? now()->format('Y-m-d'),
            'file_path'          => $bookData['filePath'] ?? null,
            'file_original_name' => $bookData['fileOriginalName'] ?? null,
            'file_size'           => $bookData['fileSize'] ?? null,
            // Null where the mark could not be written, which is what the
            // backfill command looks for.
            'watermarked_at'      => $bookData['watermarkedAt'] ?? null,
            'access_level'        => $bookData['accessLevel'] ?? Bluebook::ACCESS_PUBLIC,
            'access_parts'        => $bookData['accessParts'] ?? null,
            'waiver_requested_at' => $bookData['waiverRequestedAt'] ?? null,
            'waiver_recorded_at'  => $bookData['waiverRecordedAt'] ?? null,
        ]);
        return self::bookToArray($b);
    }

    public static function updateBluebook(int $id, array $fields): void
    {
        $b = Bluebook::find($id);
        if (!$b) return;
        $map = ['title','authors','year','department','program','keywords','abstract','adviser','status','pages'];
        foreach ($map as $col) {
            if (array_key_exists($col, $fields)) $b->$col = $fields[$col];
        }
        if (array_key_exists('rejectionReason', $fields)) $b->rejection_reason = $fields['rejectionReason'];
        if (array_key_exists('filePath', $fields)) {
            $b->file_path          = $fields['filePath'];
            $b->file_original_name = $fields['fileOriginalName'] ?? null;
            $b->file_size          = $fields['fileSize'] ?? null;
            // The mark belongs to the file, so replacing the file replaces it -
            // and a replacement that could not be stamped must not inherit the
            // old file's claim to have been.
            $b->watermarked_at     = $fields['watermarkedAt'] ?? null;
        }
        if (array_key_exists('accessLevel', $fields)) {
            $b->access_level = $fields['accessLevel'];
            $b->access_parts = $fields['accessParts'] ?? null;
            $b->waiver_recorded_at = now();
        }
        $b->save();
    }

    public static function setBluebookStatus(int $id, string $status): void
    {
        Bluebook::where('id', $id)->update(['status' => $status]);
    }

    /**
     * Permanently remove a bluebook, its stored PDF and any bookmarks of it.
     *
     * Returns the record as it was, so the caller can name it in the audit log
     * after the row is gone. Null if there was nothing to delete.
     *
     * The file is removed before the row: an orphaned row would leave the
     * archive listing a document that 404s, whereas an orphaned file is merely
     * wasted space. Audit log entries are deliberately left alone — they record
     * that the document was accessed while it existed, which stays true.
     */
    public static function deleteBluebook(int $id): ?array
    {
        $bluebook = Bluebook::find($id);
        if (!$bluebook) {
            return null;
        }

        $snapshot = self::bookToArray($bluebook);

        if ($bluebook->file_path) {
            Storage::disk(self::bluebookDisk())->delete($bluebook->file_path);
        }

        Bookmark::where('bluebook_id', $id)->delete();
        $bluebook->delete();

        return $snapshot;
    }

    public static function incrementViews(int $id): void
    {
        Bluebook::where('id', $id)->increment('views');
    }

    public static function getPendingCount(): int
    {
        return Bluebook::where('status', 'Pending')->count();
    }

    public static function getYears(): array
    {
        return Bluebook::select('year')->distinct()->orderByDesc('year')->pluck('year')->toArray();
    }

    // ─── Logs ─────────────────────────────────────────────────────────────────

    public static function getLogs(?string $search = null, ?string $action = null): array
    {
        $query = Log::query()->latest('id');
        if ($search) {
            $q = "%$search%";
            $query->where(fn($sq) =>
                $sq->where('user_name', 'LIKE', $q)
                   ->orWhere('email',    'LIKE', $q)
                   ->orWhere('document', 'LIKE', $q)
            );
        }
        if ($action) $query->where('action', $action);
        return $query->get()->map(fn($l) => self::logToArray($l))->toArray();
    }

    public static function getLogsByEmail(string $email): array
    {
        return Log::where('email', $email)->latest('id')->get()->map(fn($l) => self::logToArray($l))->toArray();
    }

    public static function getRecentLogs(int $limit = 5): array
    {
        return Log::latest('id')->limit($limit)->get()->map(fn($l) => self::logToArray($l))->toArray();
    }

    public static function addLog(array $logData): void
    {
        Log::create([
            'user_name' => $logData['userName'],
            'email'     => $logData['email'],
            'action'    => $logData['action'],
            'document'  => $logData['document'] ?? '—',
            'timestamp' => self::now(),
            'status'    => $logData['status'] ?? 'Success',
        ]);
    }

    public static function getLogActions(): array
    {
        return Log::select('action')->distinct()->pluck('action')->toArray();
    }

    // ─── Bookmarks ────────────────────────────────────────────────────────────

    /**
     * A reader's saved papers, most recently saved first.
     *
     * These used to come back in bluebook id order - the order the archive
     * received them, which has nothing to do with the order this reader saved
     * them in, so a paper bookmarked this morning could sit at the bottom of
     * the list under one saved a year ago. Each carries the date it was saved.
     */
    public static function getBookmarkedBluebooks(string $userEmail): array
    {
        $bookmarks = Bookmark::where('user_email', $userEmail)
            ->orderByDesc('added_at')
            ->orderByDesc('id')          // same-day saves keep the order they were made
            ->get();

        if ($bookmarks->isEmpty()) {
            return [];
        }

        $books = Bluebook::whereIn('id', $bookmarks->pluck('bluebook_id'))->get()->keyBy('id');

        $out = [];
        foreach ($bookmarks as $bookmark) {
            $book = $books->get($bookmark->bluebook_id);
            if (!$book) {
                continue;                // deleted out from under the bookmark
            }
            $out[] = self::bookToArray($book) + ['bookmarkedAt' => $bookmark->added_at];
        }

        return $out;
    }

    public static function isBookmarked(string $userEmail, int $bluebookId): bool
    {
        return Bookmark::where('user_email', $userEmail)->where('bluebook_id', $bluebookId)->exists();
    }

    public static function addBookmark(string $userEmail, string $userName, int $bluebookId): void
    {
        Bookmark::firstOrCreate(
            ['user_email' => $userEmail, 'bluebook_id' => $bluebookId],
            ['user_name'  => $userName,  'added_at'    => self::now()]
        );
    }

    public static function removeBookmark(string $userEmail, int $bluebookId): void
    {
        Bookmark::where('user_email', $userEmail)->where('bluebook_id', $bluebookId)->delete();
    }

    // ─── Private Helpers ──────────────────────────────────────────────────────

    private static function userToArray(User $u): array
    {
        return [
            'id'        => $u->id,
            'name'      => $u->name,
            'email'     => $u->email,
            'role'      => $u->role,
            'canUpload' => (bool) $u->can_upload,
            'createdAt' => $u->created_at ? $u->created_at->format('Y-m-d') : now()->format('Y-m-d'),
        ];
    }

    private static function bookToArray(Bluebook $b): array
    {
        return [
            'id'             => $b->id,
            'title'          => $b->title,
            'authors'        => $b->authors ?? [],
            'year'           => $b->year,
            'department'     => $b->department,
            'program'        => $b->program,
            'keywords'       => $b->keywords ?? [],
            'abstract'       => $b->abstract,
            'adviser'        => $b->adviser,
            'status'         => $b->status,
            'uploadedBy'     => $b->uploaded_by,
            'uploadedByName' => $b->uploaded_by_name,
            'pages'          => $b->pages,
            'views'          => $b->views,
            'dateAdded'      => $b->date_added,
            'filePath'         => $b->file_path,
            'fileOriginalName' => $b->file_original_name,
            'fileSize'         => $b->file_size,
            'hasFile'          => !empty($b->file_path),
            'ocrStatus'        => $b->ocr_status,
            'ocrStuck'         => $b->isOcrStuck(),
            'ocrText'          => $b->ocr_text,
            'ocrError'         => $b->ocr_error,
            'ocrEngine'        => $b->ocr_engine,
            'ocrRasterizer'    => $b->ocr_rasterizer,
            'ocrProcessedAt'   => $b->ocr_processed_at ? $b->ocr_processed_at->format('Y-m-d H:i:s') : null,
            'accessLevel'      => $b->access_level ?: Bluebook::ACCESS_PUBLIC,
            'accessParts'      => $b->access_parts ?? [],
            'waiverRecorded'   => $b->waiver_recorded_at !== null,
            'waiverRequested'  => $b->waiver_requested_at !== null,
            'rejectionReason'  => $b->rejection_reason,
        ];
    }

    private static function logToArray(Log $l): array
    {
        return [
            'id'        => $l->id,
            'userName'  => $l->user_name,
            'email'     => $l->email,
            'action'    => $l->action,
            'document'  => $l->document,
            'timestamp' => $l->timestamp,
            'status'    => $l->status,
        ];
    }
}
