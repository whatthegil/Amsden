<?php

namespace App\Http\Controllers\Concerns;

use App\Services\Pdf\PdfWatermarker;
use App\Services\Store;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Sends a bluebook's PDF to the person reading it: cut to the pages the waiver
 * permits, stamped with who asked for it, and served so the viewer can start
 * drawing before the whole file has arrived. Shared by the reader's route and
 * the admin's, which differ only in what they allow through before this point.
 *
 * Preparing a copy is the slow part - fetching the stored file, cutting it, and
 * rewriting every page with the reader's mark took 12 seconds for a 15 MB paper
 * on Laravel Cloud, on every single open. So the prepared copy is kept on local
 * disk for a day, keyed by everything that decides its bytes: the paper, the
 * stored file, the permitted pages and, when it is stamped, the reader. A reader
 * reopening a paper is then served at once.
 *
 * It is served as a file, with its length and byte ranges, rather than streamed
 * chunked: PDF.js reads the length, asks for the parts it needs, and draws page
 * one after a few hundred kilobytes instead of after the whole document.
 */
trait StreamsBluebookDocument
{
    /** How long a prepared copy is kept, in seconds. */
    private static int $documentCacheTtl = 86400;

    /** The most the cache may hold before the oldest copies go, in bytes. */
    private static int $documentCacheCap = 2 * 1024 * 1024 * 1024;

    /**
     * @param string|null $pageList The pages the waiver permits ("1-5,9-20"),
     *                              or null for the whole document.
     */
    private function streamBluebookDocument(array $bluebook, array $user, Filesystem $disk, ?string $pageList)
    {
        // A viewer asking for a range is reading on in a document it already
        // opened. Only the opening request is the document leaving.
        if (!request()->headers->has('Range')) {
            Store::addLog([
                'userName' => $user['name'] ?? 'Unknown',
                'email'    => $user['email'] ?? 'Unknown',
                'action'   => 'Downloaded Bluebook File',
                'document' => $bluebook['title'],
            ]);
        }

        $stamp = (bool) config('watermark.per_viewer', true);
        $path  = $this->documentCachePath($bluebook, $pageList, $stamp ? (string) ($user['email'] ?? '') : null);

        if (!$this->isFreshDocument($path)) {
            // One reader opening a paper sends several requests at once; only
            // the first should prepare the copy, the rest wait for it.
            Cache::lock('bluebook-doc:' . basename($path), 180)->block(170, function () use ($path, $bluebook, $pageList, $stamp, $user) {
                if (!$this->isFreshDocument($path)) {
                    $this->prepareDocument($path, $bluebook, $pageList, $stamp, $user);
                }
            });
        }

        if (!$this->isFreshDocument($path)) {
            // Nothing could be prepared. A partial document is never replaced
            // by the whole one: that would hand over the pages being withheld.
            if ($pageList !== null) {
                abort(503, 'The permitted parts of this bluebook could not be prepared. Please try again later.');
            }
            return $this->streamStoredDocument($bluebook, $disk);
        }

        $response = new BinaryFileResponse($path, 200, [
            'Content-Type'           => 'application/pdf',
            'Cache-Control'          => 'private, no-store',
            'X-Frame-Options'        => 'SAMEORIGIN',
            'X-Content-Type-Options' => 'nosniff',
        ], false, null, false, false);
        $response->setContentDisposition('inline', $this->safeFilename($bluebook));

        return $response;
    }

    /** Where the copy for this paper, these pages and this reader lives. */
    private function documentCachePath(array $bluebook, ?string $pageList, ?string $viewer): string
    {
        $key = sha1(implode('|', [
            $bluebook['id'], $bluebook['filePath'], $bluebook['fileSize'] ?? '',
            $pageList ?? 'all', $viewer ?? 'unstamped',
            config('watermark.opacity'), config('watermark.size'), 'v1',
        ]));

        return storage_path('app/document-cache/' . $bluebook['id'] . '-' . $key . '.pdf');
    }

    private function isFreshDocument(string $path): bool
    {
        clearstatcache(true, $path);

        return is_file($path) && filesize($path) > 0 && filemtime($path) > time() - self::$documentCacheTtl;
    }

    /**
     * Cut, stamp and keep the copy. A stamp that will not take leaves the copy
     * unmade rather than cached unstamped, so the next request tries again;
     * the reader is served the unstamped document meanwhile, as before.
     */
    private function prepareDocument(string $path, array $bluebook, ?string $pageList, bool $stamp, array $user): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $work = $pageList !== null
            ? PdfWatermarker::keepPagesToTemp($bluebook['filePath'], $pageList)
            : PdfWatermarker::pullToTemp($bluebook['filePath']);

        if ($work === null) {
            return;
        }

        if ($stamp) {
            $lines   = PdfWatermarker::viewerLines($user);
            $stamped = $work . '.stamped.pdf';

            if (PdfWatermarker::stampFile($work, $stamped, $lines[0], $lines[1], PdfWatermarker::VIEWER_OFFSET, true)) {
                @unlink($work);
                $work = $stamped;
            } elseif ($pageList === null) {
                // Unstamped whole documents are served from storage, not kept.
                @unlink($work);
                @unlink($stamped);
                return;
            } else {
                @unlink($stamped);
            }
        }

        if (!@rename($work, $path)) {
            @unlink($work);
            Log::warning('[document-cache] could not keep a prepared copy', ['path' => basename($path)]);
            return;
        }

        $this->pruneDocumentCache($dir);
    }

    /** Drop copies past their day, then the oldest until the cache fits its cap. */
    private function pruneDocumentCache(string $dir): void
    {
        $files = [];
        foreach (glob($dir . DIRECTORY_SEPARATOR . '*.pdf') ?: [] as $file) {
            $mtime = @filemtime($file);
            if ($mtime === false) continue;
            if ($mtime <= time() - self::$documentCacheTtl) {
                @unlink($file);
                continue;
            }
            $files[$file] = [$mtime, (int) @filesize($file)];
        }

        $total = array_sum(array_column($files, 1));
        if ($total <= self::$documentCacheCap) {
            return;
        }

        uasort($files, fn($a, $b) => $a[0] <=> $b[0]);
        foreach ($files as $file => [, $size]) {
            @unlink($file);
            $total -= $size;
            if ($total <= self::$documentCacheCap) break;
        }
    }

    private function safeFilename(array $bluebook): string
    {
        $name = preg_replace('/[^\w\s.-]/u', '', (string) ($bluebook['fileOriginalName'] ?? '')) ?: 'document.pdf';

        return \Illuminate\Support\Str::ascii($name) ?: 'document.pdf';
    }

    /**
     * The stored file straight from the disk, for when no copy could be kept.
     *
     * Streamed chunked rather than through Storage::response(), which sets
     * Content-Length from the object's recorded size and streams the body
     * separately; when the two disagree nginx aborts mid-flight with "upstream
     * sent more data than specified in Content-Length".
     */
    private function streamStoredDocument(array $bluebook, Filesystem $disk)
    {
        $stream = $disk->readStream($bluebook['filePath']);
        if ($stream === false || $stream === null) {
            abort(404);
        }

        return response()->stream(function () use ($stream) {
            @set_time_limit(0);
            while (!feof($stream)) {
                $chunk = fread($stream, 262144);
                if ($chunk === false) break;
                echo $chunk;
                if (ob_get_level() > 0) @ob_flush();
                flush();
            }
            fclose($stream);
        }, 200, [
            'Content-Type'           => 'application/pdf',
            'Content-Disposition'    => 'inline; filename="' . $this->safeFilename($bluebook) . '"',
            'Cache-Control'          => 'no-store',
            'X-Frame-Options'        => 'SAMEORIGIN',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
