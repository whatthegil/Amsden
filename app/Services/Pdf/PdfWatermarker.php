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

    /** Gap between marks, in points. Matches STEP in resources/pdf/watermark.js. */
    private const TILE = 300;

    /** Is there a binary that can do this? */
    public static function available(): bool
    {
        return self::stamper() !== null;
    }

    /**
     * Which tool will do the stamping, and how.
     *
     * MuPDF first because it edits the document in place: the original objects
     * are kept and a content stream is appended to each page. Ghostscript
     * cannot do that - pdfwrite rebuilds the file from its own interpretation
     * of the input - but it is what the Laravel Cloud container actually has,
     * and a rebuilt PDF carrying the mark beats a pristine one carrying
     * nothing.
     *
     * @return array{kind: string, bin: string}|null
     */
    private static function stamper(): ?array
    {
        if ($bin = self::mutool()) {
            return ['kind' => 'mutool', 'bin' => $bin];
        }

        if ($bin = self::ghostscript()) {
            return ['kind' => 'gs', 'bin' => $bin];
        }

        return null;
    }

    /** The name of the tool that would be used, for diagnostics. */
    public static function backend(): ?string
    {
        return self::stamper()['kind'] ?? null;
    }

    private static function ghostscript(): ?string
    {
        return BinaryFinder::find(config('ocr.ghostscript_path'), ['gs', 'gswin64c', 'gswin32c'], [
            'C:\\Program Files\\gs\\gs*\\bin\\gswin64c.exe',
            'C:\\Program Files (x86)\\gs\\gs*\\bin\\gswin32c.exe',
        ]);
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
        $stamper = self::stamper();

        if ($stamper === null) {
            Log::warning('[watermark] no PDF binary available; document left unstamped', ['src' => basename($src)]);
            return false;
        }

        if ($stamper['kind'] === 'gs') {
            return self::stampWithGhostscript($stamper['bin'], $src, $dest, $line1, $line2, $offset);
        }

        $bin    = $stamper['bin'];
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

    /**
     * Stamp with Ghostscript, by giving it a page hook rather than a script.
     *
     * pdfwrite re-interprets and rewrites the whole file, so unlike the MuPDF
     * path nothing of the original structure is preserved - but the text and
     * images come through, and the mark ends up in the page content where it
     * belongs.
     *
     * The drawing is installed as /EndPage, which the interpreter calls as each
     * page finishes, with the page count and a reason code on the stack. Reason
     * 2 means the page is being discarded by a device change, so it is passed
     * through untouched; anything else is a real page and gets the mark. The
     * procedure has to leave a boolean behind saying whether to emit the page,
     * which is what the `dup ... if` is doing - the copy feeds the `if`, the
     * original is the return value.
     */
    private static function stampWithGhostscript(
        string $bin, string $src, string $dest, string $line1, string $line2, float $offset
    ): bool {
        $size    = (float) config('watermark.size', 11);
        $opacity = (float) config('watermark.opacity', 0.13);

        $program = sprintf(
            '<< /EndPage { exch pop 2 ne dup { gsave '
            // Tile against the real page, not an assumed one: this archive has
            // A4 and Letter in it, and pages the scanner left at odd sizes.
            . 'currentpagedevice /PageSize get aload pop /ph exch def /pw exch def '
            // Transparency is a Ghostscript extension. Where it is missing the
            // mark would otherwise be laid down opaque in navy, straight over
            // the words underneath, so fall back to a pale ink instead of a
            // dark one at an opacity that was never applied.
            . 'systemdict /.setopacityalpha known '
            . '{ %.2F .setopacityalpha 0.06 0.14 0.31 setrgbcolor } '
            . '{ 0.87 0.88 0.92 setrgbcolor } ifelse '
            . '/Helvetica findfont %.1F scalefont setfont '
            . '%.1F %d ph %d add { /yy exch def '
            . '%.1F %d pw %d add { /xx exch def '
            . 'gsave xx yy translate -22 rotate '
            . '0 0 moveto (%s) show '
            . '0 -%.1F moveto (%s) show '
            . 'grestore } for } for '
            . 'grestore } if } >> setpagedevice',
            $opacity,
            $size,
            40.0 + $offset, self::TILE, self::TILE,
            20.0 + $offset, self::TILE, self::TILE,
            self::escapePostScript($line1),
            $size + 3,
            self::escapePostScript($line2)
        );

        $process = new Process([
            $bin, '-q', '-dBATCH', '-dNOPAUSE', '-dSAFER',
            '-sDEVICE=pdfwrite',
            // Keep the text as text. Without this Ghostscript is free to turn
            // an awkward font into outlines or a bitmap, and a thesis that
            // arrives as pictures of words cannot be searched or read aloud.
            '-dSubsetFonts=true', '-dEmbedAllFonts=true',
            '-o', $dest,
            '-c', $program,
            '-f', $src,
        ]);
        $process->setTimeout((float) config('watermark.timeout', 120));

        try {
            $process->mustRun();
        } catch (ProcessFailedException $e) {
            Log::error('[watermark] ghostscript stamping failed', [
                'src'   => basename($src),
                'error' => trim($process->getErrorOutput() ?: $e->getMessage()),
            ]);
            return false;
        }

        if (!is_file($dest) || filesize($dest) < 1024) {
            Log::error('[watermark] ghostscript produced no usable file', ['dest' => basename($dest)]);
            @unlink($dest);
            return false;
        }

        return true;
    }

    /**
     * A PostScript string literal ends at its first unescaped bracket, the same
     * as a PDF one - so a title carrying one would truncate the mark and leave
     * the rest of the program as garbage.
     */
    private static function escapePostScript(string $text): string
    {
        $out = '';

        foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $char) {
            $code = mb_ord($char, 'UTF-8');

            if ($char === '(' || $char === ')' || $char === '\\') {
                $out .= '\\' . $char;
            } elseif ($code !== false && $code >= 32 && $code < 127) {
                $out .= $char;
            } elseif ($code !== false && $code < 256) {
                // Octal, for the same reason the MuPDF script uses it: the
                // program travels through an argument that is re-encoded on the
                // way in, and an escape survives that where a raw byte does not.
                $out .= '\\' . str_pad(decoct($code), 3, '0', STR_PAD_LEFT);
            }
            // Anything with no single-byte form is dropped rather than mangled.
        }

        return $out;
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

    /**
     * Stamp a document this makes up, and report what happened.
     *
     * The Ghostscript path cannot be tried on the machine it was written on -
     * there is no Ghostscript here - and the only host that has it is the one
     * holding the live archive. Proving it there by stamping a real thesis
     * would mean finding out it was wrong by damaging one. This makes its own
     * PDF instead, so the backend can be exercised on the host that will run
     * it, against a file nobody needs.
     *
     * @return array<string,mixed>
     */
    public static function selfTest(): array
    {
        $backend = self::backend();

        if ($backend === null) {
            return ['ok' => false, 'backend' => null, 'reason' => 'no stamping binary on this host'];
        }

        $src  = self::tempPath();
        $dest = self::tempPath();

        file_put_contents($src, self::samplePdf());

        try {
            $ok = self::stampFile($src, $dest, 'CSPC ARCHIVE SELFTEST', 'stamped by ' . $backend);

            if (!$ok) {
                return ['ok' => false, 'backend' => $backend, 'reason' => 'the stamper reported failure (see the log)'];
            }

            $result = [
                'ok'        => true,
                'backend'   => $backend,
                'src_bytes' => filesize($src),
                'out_bytes' => filesize($dest),
                'is_pdf'    => str_starts_with((string) file_get_contents($dest, false, null, 0, 5), '%PDF'),
            ];

            // Whether the mark RENDERS, not whether a tool can read it back as
            // characters. Those are different questions, and the first attempt
            // asked the wrong one: pdftotext reports nothing for a mark MuPDF
            // extracts six times a page, because the font the stamper adds
            // carries no ToUnicode map and Poppler cannot map the glyphs back.
            // The mark was on the page the whole time. Ink on the page is the
            // thing that matters and the thing every backend can be asked
            // about, so count that instead.
            $before = self::inkFraction($src);
            $after  = self::inkFraction($dest);

            $result['ink_before'] = $before;
            $result['ink_after']  = $after;
            $result['mark_drawn'] = ($before !== null && $after !== null) ? $after > $before + 0.001 : null;

            // The document's own words are still worth checking as text: if
            // they survive extraction they certainly survived stamping.
            $pdftotext = BinaryFinder::find(config('ocr.pdftotext_path'), ['pdftotext'], []);

            if ($pdftotext) {
                $txt = self::tempPath() . '.txt';
                $p   = new Process([$pdftotext, '-q', $dest, $txt]);
                $p->setTimeout(30);
                $p->run();

                $text = is_file($txt) ? (string) file_get_contents($txt) : '';
                @unlink($txt);

                $result['content_survived'] = str_contains($text, 'Original text');
            } else {
                $result['content_survived'] = null;
            }

            return $result;
        } finally {
            @unlink($src);
            @unlink($dest);
        }
    }

    /**
     * How much of page one is not white, as a fraction.
     *
     * Rendered to a grey PGM because that format is a short ASCII header
     * followed by one byte per pixel - no image library needed, which matters
     * on a host whose PHP build is not ours to choose.
     *
     * Null when nothing here can rasterize, which is not a failure: it means
     * this check cannot be run, and saying so is better than guessing.
     */
    private static function inkFraction(string $pdf): ?float
    {
        $out = self::tempPath() . '.pgm';

        if ($bin = self::mutool()) {
            $cmd = [$bin, 'draw', '-F', 'pgm', '-r', '50', '-o', $out, $pdf, '1'];
        } elseif ($bin = BinaryFinder::find(config('ocr.pdftoppm_path'), ['pdftoppm'], [])) {
            // pdftoppm appends its own -1 and extension to the prefix given.
            $prefix = self::tempPath();
            $cmd    = [$bin, '-gray', '-r', '50', '-f', '1', '-l', '1', $pdf, $prefix];
            $out    = $prefix . '-1.pgm';
        } elseif ($bin = self::ghostscript()) {
            $cmd = [$bin, '-q', '-dBATCH', '-dNOPAUSE', '-dSAFER', '-sDEVICE=pgmraw',
                    '-r50', '-dFirstPage=1', '-dLastPage=1', '-o', $out, $pdf];
        } else {
            return null;
        }

        try {
            $p = new Process($cmd);
            $p->setTimeout(60);
            $p->run();
        } catch (\Throwable $e) {
            return null;
        }

        if (!is_file($out)) {
            return null;
        }

        try {
            return self::darkFractionOfPgm((string) file_get_contents($out));
        } finally {
            @unlink($out);
        }
    }

    /** P5 greymap: "P5", width, height, maxval, then one byte per pixel. */
    private static function darkFractionOfPgm(string $pgm): ?float
    {
        if (!str_starts_with($pgm, 'P5')) {
            return null;
        }

        // Three whitespace-separated numbers follow the magic, with comment
        // lines allowed anywhere between them.
        $offset = 2;
        $fields = [];

        while (count($fields) < 3 && $offset < strlen($pgm)) {
            $char = $pgm[$offset];

            if (ctype_space($char)) {
                $offset++;
            } elseif ($char === '#') {
                $offset = strpos($pgm, "\n", $offset) ?: strlen($pgm);
            } else {
                $end = $offset;
                while ($end < strlen($pgm) && !ctype_space($pgm[$end])) {
                    $end++;
                }
                $fields[] = (int) substr($pgm, $offset, $end - $offset);
                $offset   = $end;
            }
        }

        if (count($fields) < 3) {
            return null;
        }

        $pixels = substr($pgm, $offset + 1);
        $total  = strlen($pixels);

        if ($total === 0) {
            return null;
        }

        // Anything below near-white counts. The mark is pale by design, so the
        // threshold has to sit close to white or it would not see it at all.
        $dark = 0;
        for ($i = 0; $i < $total; $i++) {
            if (ord($pixels[$i]) < 250) {
                $dark++;
            }
        }

        return $dark / $total;
    }

    /** A minimal, valid, one-page PDF with a line of text on it. */
    private static function samplePdf(): string
    {
        // Measured, not counted by hand. The first version of this declared 52
        // for a 45-byte stream, and MuPDF said so - "PDF stream Length
        // incorrect" - then stamped the page without drawing the mark. The
        // self-test correctly reported a failure that was entirely this
        // function's fault, which is a good way to lose an afternoon
        // suspecting the stamper.
        $content = "BT /F1 14 Tf 72 700 Td (Original text) Tj ET\n";

        $objects = [
            "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n",
            "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n",
            "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R"
                . " /Resources << /Font << /F1 5 0 R >> >> >>\nendobj\n",
            "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n" . $content . "endstream\nendobj\n",
            "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n",
        ];

        $pdf     = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $i => $object) {
            $offsets[$i + 1] = strlen($pdf);
            $pdf .= $object;
        }

        $start = strlen($pdf);
        $pdf  .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";

        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }

        return $pdf . "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n"
            . "startxref\n{$start}\n%%EOF\n";
    }

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
