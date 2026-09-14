<?php

namespace App\Console\Commands;

use App\Services\Ocr\BinaryFinder;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

/**
 * What this host can and cannot do.
 *
 * Several features here are not written in PHP at all - they shell out to a
 * binary and fail if it is absent. On a managed container that is easy to miss:
 * nothing errors at deploy time, uploads still succeed, and the pages still
 * render. What happens instead is quieter. OCR throws inside a queued job, so
 * papers get no extracted text and the searches that read it return nothing;
 * the watermarker logs and serves the file unstamped.
 *
 * This asks every question at once, and says what is lost for each answer
 * rather than only which command is missing.
 */
class SystemCheck extends Command
{
    protected $signature = 'system:check';
    protected $description = 'Report which external tools this host has, and what stops working without them';

    /** Every binary the app shells out to, and where an explicit path can be pinned. */
    private const TOOLS = [
        'mutool'    => ['names' => ['mutool'], 'pin' => 'ocr.mutool_path',
                        'globs' => ['C:\\Program Files\\mupdf\\mutool.exe']],
        'gs'        => ['names' => ['gs', 'gswin64c', 'gswin32c'], 'pin' => 'ocr.ghostscript_path',
                        'globs' => ['C:\\Program Files\\gs\\gs*\\bin\\gswin64c.exe']],
        'pdftoppm'  => ['names' => ['pdftoppm'], 'pin' => 'ocr.pdftoppm_path', 'globs' => []],
        'pdftotext' => ['names' => ['pdftotext'], 'pin' => 'ocr.pdftotext_path', 'globs' => []],
        'tesseract' => ['names' => ['tesseract'], 'pin' => 'ocr.tesseract_path', 'globs' => []],
        'qpdf'      => ['names' => ['qpdf'], 'pin' => null, 'globs' => []],
    ];

    public function handle(): int
    {
        $found = [];

        foreach (self::TOOLS as $name => $spec) {
            $path = BinaryFinder::find(
                $spec['pin'] ? config($spec['pin']) : null,
                $spec['names'],
                $spec['globs']
            );
            $found[$name] = $path;
        }

        $this->newLine();
        $this->line('<options=bold>C-BAMS external tools</> — ' . PHP_OS_FAMILY . ', PHP ' . PHP_VERSION);
        $this->line(str_repeat('─', 62));

        foreach ($found as $name => $path) {
            $this->line(sprintf(
                '  %-11s %s %s',
                $name,
                $path ? '<fg=green>' . str_pad('found', 14) . '</>' : '<fg=red>' . str_pad('not installed', 14) . '</>',
                $path ? ($this->versionOf($path) ?? $path) : ''
            ));
        }

        $this->newLine();
        $this->line('<options=bold>What that means</>');
        $this->line(str_repeat('─', 62));

        $rasterizer = $found['mutool'] ?? $found['gs'] ?? $found['pdftoppm'];
        $engine     = $found['tesseract'] ?? (PHP_OS_FAMILY === 'Windows' ? 'windows-ocr' : null);
        $stamper    = $found['mutool'] ?? $found['qpdf'] ?? $found['gs'];

        $failed = 0;

        // OCR needs both halves: something to turn pages into images, and
        // something to read them. Either one missing stops the whole pipeline.
        if ($rasterizer && $engine) {
            $this->capability('Searchable text (OCR)', true, [
                'Uploaded papers get their text extracted, so document-content search works.',
            ]);
        } else {
            $failed++;
            $missing = [];
            if (!$rasterizer) $missing[] = 'no rasterizer (needs one of mutool, gs, pdftoppm)';
            if (!$engine)     $missing[] = 'no OCR engine (needs tesseract; the Windows fallback is Windows-only)';
            $this->capability('Searchable text (OCR)', false, array_merge($missing, [
                'Uploaded papers get no extracted text at all - the job throws and the',
                'record lands on ocr_status=failed.',
                'Searching document contents returns nothing, the similarity checker',
                'loses the signal it weights most heavily, and literature-review',
                'summaries fall back to the abstract alone.',
            ]));
        }

        if ($stamper) {
            $this->capability('Document watermarking', true, [
                'Stored PDFs carry the archive mark, and served copies name the reader.',
            ]);
        } else {
            $failed++;
            $this->capability('Document watermarking', false, [
                'no stamper (needs one of mutool, qpdf, gs)',
                'Stored PDFs are served unmarked, so a copy taken from the file route',
                'carries nothing identifying it.',
                'The on-screen watermark is unaffected - it is drawn in the browser, and',
                'it is the layer a screenshot captures.',
            ]);
        }

        // Optional: only a shortcut, and its absence costs time rather than function.
        if ($found['pdftotext']) {
            $this->capability('Similarity fast path', true, [
                'A pre-proposal with a text layer is read directly instead of being OCR\'d.',
            ]);
        } else {
            $this->capability('Similarity fast path', null, [
                'pdftotext is missing, so every uploaded pre-proposal goes through OCR.',
                'Slower, and it needs the OCR tools above, but nothing is lost when they exist.',
            ]);
        }

        $this->newLine();

        if ($failed > 0) {
            $this->warn("{$failed} " . ($failed === 1 ? 'capability is' : 'capabilities are') . ' unavailable on this host.');
            $this->line('Install the packages named above, or pin an existing install with the');
            $this->line('OCR_*_PATH variables in .env. On Debian/Ubuntu the usual names are:');
            $this->line('  mupdf-tools (mutool)   ghostscript (gs)   poppler-utils (pdftoppm, pdftotext)');
            $this->line('  tesseract-ocr          qpdf');

            return self::FAILURE;
        }

        $this->info('Everything this app shells out to is present.');

        return self::SUCCESS;
    }

    /** @param  array<int,string>  $lines */
    private function capability(string $name, ?bool $ok, array $lines): void
    {
        $label = $ok === true ? '<fg=green>available</>'
               : ($ok === false ? '<fg=red>UNAVAILABLE</>' : '<fg=yellow>degraded</>');

        $this->newLine();
        $this->line("  <options=bold>{$name}</>  {$label}");

        foreach ($lines as $line) {
            $this->line("    {$line}");
        }
    }

    private function versionOf(string $bin): ?string
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
}
