<?php

namespace App\Services\Pdf;

use App\Services\Ocr\BinaryFinder;
use App\Services\Store;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Writes a watermark into a PDF rather than over it on screen.
 *
 * The viewer draws the reader's identity into every page it renders, which
 * survives a screenshot. It does nothing for the file itself: anyone who
 * fetched the document route got the stored PDF, unmarked, and could pass it on
 * as though it had never been through here.
 *
 * Two marks are applied, for two different questions. The stored file is
 * stamped once at upload with where the document came from, so any copy of it
 * is identifiably CSPC's. What is served is stamped again, per request, with
 * who asked for it, so a leaked file names the account that fetched it.
 *
 * Every document in this archive is PDF 1.5 or 1.7 with object streams, which
 * rules out the PHP libraries that can only parse up to 1.4, so the work is
 * done by MuPDF through a script this ships (resources/pdf/watermark.js). Where
 * no such binary exists the document is served unstamped rather than not at
 * all - it is a protection, and losing it must not lose the archive with it.
 */
class PdfWatermarker
{
    /**
     * Half the tile spacing in watermark.js, so the second mark on a document
     * falls between the first's rather than on top of it. Stamped both ways at
     * the same phase the two overprint and neither can be read.
     */
    public const VIEWER_OFFSET = 150.0;

    /** Is there a binary that can do this? */
    public static function available(): bool
    {
        return self::mutool() !== null;
    }

    /**
     * What this host actually has, rather than what it lacks.
     *
     * "No MuPDF binary found" says the one tool we look for is absent; it does
     * not say whether the host has another that would do, or none at all. On a
     * managed container that is the difference between a backend worth writing
     * and a dead end, so the question gets asked of every candidate at once.
     */
    public static function hostReport(): array
    {
        $candidates = [
            'mutool' => ['names' => ['mutool'], 'note' => 'MuPDF - what the stamper uses today'],
            'qpdf'   => ['names' => ['qpdf'],   'note' => 'can overlay one PDF onto another'],
            'gs'     => ['names' => ['gs', 'gswin64c', 'gswin32c'], 'note' => 'Ghostscript - can draw on each page'],
            'pdftk'  => ['names' => ['pdftk'],  'note' => 'can stamp, rarely packaged now'],
            'pdftoppm' => ['names' => ['pdftoppm'], 'note' => 'Poppler - rasterises only, cannot stamp'],
        ];

        $report = [];

        foreach ($candidates as $key => $spec) {
            $path = BinaryFinder::find(null, $spec['names'], []);
            $report[$key] = [
                'found'   => $path !== null,
                'path'    => $path,
                'version' => $path !== null ? self::versionOf($path) : null,
                'note'    => $spec['note'],
            ];
        }

        return $report;
    }

    private static function versionOf(string $bin): ?string
    {
        foreach ([['-v'], ['--version']] as $flag) {
            try {
                $p = new Process(array_merge([$bin], $flag));
                $p->setTimeout(10);
                $p->run();
                $out = trim($p->getOutput() ?: $p->getErrorOutput());
                if ($out !== '') {
                    return trim(strtok($out, "\n"));
                }
            } catch (\Throwable $e) {
                // try the next flag
            }
        }

        return null;
    }

    private static function mutool(): ?string
    {
        // Same binary the OCR rasterizer looks for, so one pin configures both.
        return BinaryFinder::find(config('ocr.mutool_path'), ['mutool'], [
            'C:\\Program Files\\mupdf\\mutool.exe',
            (getenv('LOCALAPPDATA') ?: '') . '\\Microsoft\\WinGet\\Packages\\ArtifexSoftware.mutool_*\\mupdf-*\\mutool.exe',
        ]);
    }

    /**
     * Stamp one local file to another. Both paths are absolute and local;
     * callers holding a document on a remote disk go through the helpers below.
     */
    public static function stampFile(string $src, string $dest, string $line1, string $line2 = '', float $offset = 0.0): bool
    {
        $bin = self::mutool();

        if ($bin === null) {
            Log::warning('[watermark] no PDF binary available; document left unstamped', ['src' => basename($src)]);
            return false;
        }

        $script = resource_path('pdf/watermark.js');

        if (!is_file($script)) {
            Log::error('[watermark] stamping script is missing', ['script' => $script]);
            return false;
        }

        // The text travels as arguments, never interpolated into the script, so
        // nothing a title or an email address contains can become code. It is
        // sent as UTF-8 and re-encoded for the PDF inside the script: the
        // argument is transcoded on its way into the process, so converting
        // here would be undone before it arrived.
        $process = new Process([
            $bin, 'run', $script, $src, $dest, $line1, $line2,
            (string) config('watermark.opacity', 0.13),
            (string) config('watermark.size', 11),
            (string) $offset,
        ]);
        $process->setTimeout((float) config('watermark.timeout', 120));

        try {
            $process->mustRun();
        } catch (ProcessFailedException $e) {
            Log::error('[watermark] stamping failed', [
                'src'    => basename($src),
                'error'  => trim($process->getErrorOutput() ?: $e->getMessage()),
            ]);
            return false;
        }

        // A run that reports success but writes nothing usable is worse than one
        // that fails: it would replace the archive's copy with an empty file.
        if (!is_file($dest) || filesize($dest) < 1024) {
            Log::error('[watermark] stamping produced no usable file', ['dest' => basename($dest)]);
            @unlink($dest);
            return false;
        }

        return true;
    }

    /**
     * Stamp a document held on a disk, in place.
     *
     * Used once per document, at upload. The original is replaced only after a
     * good file exists to replace it with, so a failure here leaves the archive
     * holding what it already had.
     */
    public static function stampInPlace(string $path, string $line1, string $line2 = '', float $offset = 0.0): bool
    {
        $disk = Storage::disk(Store::bluebookDisk());

        if (!$disk->exists($path)) {
            return false;
        }

        $src  = self::pullToTemp($path);
        if ($src === null) {
            return false;
        }

        $dest = self::tempPath();

        try {
            if (!self::stampFile($src, $dest, $line1, $line2, $offset)) {
                return false;
            }

            $handle = fopen($dest, 'rb');
            if ($handle === false) {
                return false;
            }

            $ok = $disk->put($path, $handle);
            if (is_resource($handle)) {
                fclose($handle);
            }

            return (bool) $ok;
        } finally {
            @unlink($src);
            @unlink($dest);
        }
    }

    /**
     * Stamp a document held on a disk into a temp file, for streaming.
     *
     * The caller owns the returned path and must delete it. Null means the
     * document should be served as it is stored.
     */
    public static function stampToTemp(string $path, string $line1, string $line2 = '', float $offset = 0.0): ?string
    {
        // Swept here rather than on a schedule, because this app does not run
        // one and these are whole documents - a handful of dropped connections
        // is already hundreds of megabytes. The cutoff is well past any live
        // request, so nothing in flight is taken.
        self::pruneTemp(60);

        $src = self::pullToTemp($path);

        if ($src === null) {
            return null;
        }

        $dest = self::tempPath();

        try {
            return self::stampFile($src, $dest, $line1, $line2, $offset) ? $dest : null;
        } finally {
            @unlink($src);
        }
    }

    /** A local copy of a document that may be sitting in object storage. */
    private static function pullToTemp(string $path): ?string
    {
        $disk   = Storage::disk(Store::bluebookDisk());
        $stream = $disk->readStream($path);

        if ($stream === false || $stream === null) {
            Log::error('[watermark] could not read the document', ['path' => $path]);
            return null;
        }

        $temp   = self::tempPath();
        $handle = fopen($temp, 'wb');

        if ($handle === false) {
            fclose($stream);
            return null;
        }

        stream_copy_to_stream($stream, $handle);
        fclose($handle);
        fclose($stream);

        return $temp;
    }

    private static function tempPath(): string
    {
        $dir = storage_path('app/watermark-tmp');

        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        return $dir . DIRECTORY_SEPARATOR . 'wm_' . bin2hex(random_bytes(8)) . '.pdf';
    }

    /**
     * Anything left behind by a request that died mid-stamp.
     *
     * These are whole documents, so they are worth tens of megabytes each and
     * cannot be left to accumulate.
     */
    public static function pruneTemp(int $olderThanMinutes = 60): int
    {
        $dir = storage_path('app/watermark-tmp');

        if (!is_dir($dir)) {
            return 0;
        }

        $cutoff  = time() - ($olderThanMinutes * 60);
        $removed = 0;

        foreach (glob($dir . DIRECTORY_SEPARATOR . 'wm_*.pdf') ?: [] as $file) {
            if (@filemtime($file) < $cutoff && @unlink($file)) {
                $removed++;
            }
        }

        return $removed;
    }

    // ── The two marks ────────────────────────────────────────────────────────

    /** Where the document came from. Written into the stored file, once. */
    public static function provenanceLines(array $bluebook): array
    {
        $title = (string) ($bluebook['title'] ?? 'Untitled');

        // A 200-character title tiled across every page is not a watermark.
        if (mb_strlen($title) > 48) {
            $title = mb_substr($title, 0, 47) . '…';
        }

        return [
            'CSPC ARCHIVE · NOT FOR REDISTRIBUTION',
            $title . ' · ' . ($bluebook['year'] ?? ''),
        ];
    }

    /**
     * Take the marks back out of text pulled from a stamped document.
     *
     * The mark is real text in the content stream, so it comes back out with
     * everything else - six times a page, which on a 180-page document is a
     * thousand injected phrases. Left in, it does two kinds of damage. It fills
     * the search index with a phrase every document contains, and it inflates
     * the similarity checker: two unrelated papers would share the identical
     * provenance line a thousand times each, which is exactly the signal that
     * checker is built to treat as copying.
     *
     * Extraction breaks a line wherever it likes, so the patterns are tolerant
     * of whitespace rather than anchored to it.
     */
    public static function stripMarks(string $text, ?array $bluebook = null): string
    {
        if (trim($text) === '') {
            return $text;
        }

        $patterns = [
            // The provenance line. The one that matters most: it is identical
            // in every document in the archive.
            '/CSPC\s*ARCHIVE\s*[·\x{2022}\-]?\s*NOT\s*FOR\s*REDISTRIBUTION/iu',
            // The per-viewer line, on a copy that was served and came back in
            // through the similarity checker. The trailing archive name is not
            // required: marks near a page edge are clipped by it, so the tail
            // is often simply not in the text. An address followed straight by
            // a timestamp is signature enough - it is not a thing prose does.
            '/[^\s@]+@[^\s@]+\.[a-z]{2,}\s*[·\x{2022}\-]?\s*\d{4}-\d{2}-\d{2}[\s\d:]*/iu',
            // Whatever is left of a bare archive name once those have gone.
            '/\bCSPC\s*ARCHIVE\b/iu',
        ];

        if ($bluebook !== null) {
            // "<title> · <year>" - the title is trimmed with an ellipsis that
            // extraction may hand back as one character or as three dots.
            $title = (string) ($bluebook['title'] ?? '');
            $head  = preg_quote(mb_substr($title, 0, 24), '/');

            if ($head !== '') {
                $patterns[] = '/' . str_replace('\ ', '\s+', $head)
                            . '[^\n]{0,28}?[·\x{2022}]\s*' . preg_quote((string) ($bluebook['year'] ?? ''), '/') . '/iu';
            }
        }

        $cleaned = preg_replace($patterns, ' ', $text);

        if ($cleaned === null) {
            return $text;       // a pattern that would not run leaves the text alone
        }

        // Close the gaps the marks left, without joining words that were never
        // together or collapsing the paragraph breaks the text depends on.
        $cleaned = preg_replace('/[ \t]{2,}/', ' ', $cleaned) ?? $cleaned;
        $cleaned = preg_replace('/\n{3,}/', "\n\n", $cleaned) ?? $cleaned;

        return trim($cleaned);
    }

    /** Who asked for it. Written into what is served, per request. */
    public static function viewerLines(?array $user): array
    {
        $who = trim((string) ($user['email'] ?? '')) ?: 'unidentified viewer';

        return [
            $who,
            Store::now() . ' · CSPC ARCHIVE',
        ];
    }
}
