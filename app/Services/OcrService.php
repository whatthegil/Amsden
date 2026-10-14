<?php

namespace App\Services;

use App\Services\Ocr\Engines\EngineResolver;
use App\Services\Ocr\Rasterizers\RasterizerResolver;
use RuntimeException;

class OcrService
{
    /**
     * Rasterize the PDF at $absolutePdfPath (via whichever rasterizer is
     * available: mutool, Ghostscript, or Poppler) and run OCR on each page
     * (via whichever engine is available: Tesseract, or the built-in Windows
     * OCR as a zero-install fallback).
     *
     * $maxPages overrides config('ocr.max_pages') for this call only — used by
     * the similarity checker's upload path, where a synchronous request can't
     * wait on a 60-page OCR run. The cap is pushed into the rasterizer so the
     * pages beyond it are never rendered in the first place.
     *
     * @return array{text: string, pages: int, engine: string, rasterizer: string}
     */
    public static function extractText(string $absolutePdfPath, ?int $maxPages = null): array
    {
        if (!is_file($absolutePdfPath)) {
            throw new RuntimeException("PDF not found: {$absolutePdfPath}");
        }

        $rasterizer = RasterizerResolver::resolve();
        $engine     = EngineResolver::resolve();

        $maxPages = $maxPages ?? config('ocr.max_pages');

        $workDir = storage_path('app/ocr-tmp/' . uniqid('ocr_', true));
        if (!mkdir($workDir, 0777, true) && !is_dir($workDir)) {
            throw new RuntimeException("Could not create OCR work directory: {$workDir}");
        }

        try {
            $pageImages = $rasterizer->rasterize($absolutePdfPath, $workDir, config('ocr.dpi'), $maxPages);

            // Belt-and-braces: a rasterizer that ignored the cap still gets trimmed.
            if ($maxPages > 0 && count($pageImages) > $maxPages) {
                $pageImages = array_slice($pageImages, 0, $maxPages);
            }

            $text   = '';
            $maxLen = config('ocr.max_text_length');

            foreach ($pageImages as $imagePath) {
                $text .= $engine->recognize($imagePath) . "\n\n";

                // Stop once the stored-text budget is spent; the remaining
                // pages would be truncated away anyway.
                if (strlen($text) >= $maxLen) {
                    break;
                }
            }

            $text = trim($text);
            if (strlen($text) > $maxLen) {
                $text = substr($text, 0, $maxLen);
            }

            return [
                'text'       => $text,
                'pages'      => count($pageImages),
                'engine'     => $engine->name(),
                'rasterizer' => $rasterizer->name(),
            ];
        } finally {
            self::cleanup($workDir);
        }
    }

    /**
     * Remove OCR work directories left behind by runs that were killed before
     * their own cleanup could run (queue worker timeout, process kill). Called
     * at the start of each queued OCR job.
     */
    public static function pruneOrphanedWorkDirs(int $olderThanSeconds = 3600): int
    {
        $root = storage_path('app/ocr-tmp');
        if (!is_dir($root)) return 0;

        $removed = 0;
        foreach (glob($root . DIRECTORY_SEPARATOR . 'ocr_*') ?: [] as $dir) {
            if (!is_dir($dir)) continue;
            if (time() - (int) @filemtime($dir) < $olderThanSeconds) continue;
            self::cleanup($dir);
            $removed++;
        }

        return $removed;
    }

    private static function cleanup(string $dir): void
    {
        if (!is_dir($dir)) return;
        foreach (glob($dir . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($dir);
    }
}
