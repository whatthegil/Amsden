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
        $user = User::where('email', $email)->first();
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
            'email'      => $userData['email'],
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
        if (isset($fields['email']))     $user->email      = $fields['email'];
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
        if (array_key_exists('filePath', $fields)) {
            $b->file_path          = $fields['filePath'];
            $b->file_original_name = $fields['fileOriginalName'] ?? null;
            $b->file_size          = $fields['fileSize'] ?? null;
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

    public static function getBookmarkedBluebooks(string $userEmail): array
    {
        $ids = Bookmark::where('user_email', $userEmail)->pluck('bluebook_id');
        return Bluebook::whereIn('id', $ids)->get()->map(fn($b) => self::bookToArray($b))->toArray();
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
