<?php

namespace App\Services;

use App\Services\Ocr\BinaryFinder;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Pulls the embedded text layer out of a PDF *without* OCR, using whichever
 * CLI tool happens to be installed (mutool → Poppler pdftotext → Ghostscript
 * txtwrite — the same binaries the OCR pipeline already relies on for
 * rasterizing). Fast enough to run inside a web request.
 *
 * Returns '' for scanned / image-only PDFs that carry no text layer; the
 * caller decides whether to fall back to the (much slower) OcrService for
 * those.
 */
class PdfTextExtractor
{
    /** Per-tool wall-clock limit — keeps a synchronous request bounded. */
    private const PROCESS_TIMEOUT = 60;

    public static function extract(string $absolutePdfPath, int $maxChars = 200000): string
    {
        if (!is_file($absolutePdfPath)) {
            return '';
        }

        $text = self::viaMutool($absolutePdfPath)
            ?? self::viaPdftotext($absolutePdfPath)
            ?? self::viaGhostscript($absolutePdfPath)
            ?? '';

        $text = self::normalize($text);

        return strlen($text) > $maxChars ? substr($text, 0, $maxChars) : $text;
    }

    private static function viaMutool(string $pdf): ?string
    {
        $bin = BinaryFinder::find(config('ocr.mutool_path'), ['mutool'], [
            'C:\\Program Files\\mupdf\\mutool.exe',
            (getenv('LOCALAPPDATA') ?: '') . '\\Microsoft\\WinGet\\Packages\\ArtifexSoftware.mutool_*\\mupdf-*\\mutool.exe',
        ]);

        return $bin ? self::run([$bin, 'draw', '-F', 'txt', '-o', '-', $pdf]) : null;
    }

    private static function viaPdftotext(string $pdf): ?string
    {
        $bin = BinaryFinder::find(config('ocr.pdftotext_path'), ['pdftotext'], [
            'C:\\poppler*\\Library\\bin\\pdftotext.exe',
            'C:\\Program Files\\poppler*\\bin\\pdftotext.exe',
        ]);

        return $bin ? self::run([$bin, '-q', '-enc', 'UTF-8', $pdf, '-']) : null;
    }

    private static function viaGhostscript(string $pdf): ?string
    {
        $bin = BinaryFinder::find(config('ocr.ghostscript_path'), ['gswin64c', 'gswin32c', 'gs'], [
            'C:\\Program Files\\gs\\gs*\\bin\\gswin64c.exe',
            'C:\\Program Files (x86)\\gs\\gs*\\bin\\gswin32c.exe',
        ]);

        return $bin ? self::run([
            $bin, '-dNOPAUSE', '-dBATCH', '-dSAFER',
            '-sDEVICE=txtwrite', '-sOutputFile=-', $pdf,
        ]) : null;
    }

    /** @param list<string> $cmd */
    private static function run(array $cmd): ?string
    {
        try {
            $process = new Process($cmd);
            $process->setTimeout(self::PROCESS_TIMEOUT);
            $process->run();

            if (!$process->isSuccessful()) {
                return null;
            }

            $out = $process->getOutput();
            return trim($out) === '' ? null : $out;
        } catch (Throwable $e) {
            return null;
        }
    }

    private static function normalize(string $text): string
    {
        $text = str_replace(["\r\n", "\r", "\f"], "\n", $text);
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text);
    }
}
