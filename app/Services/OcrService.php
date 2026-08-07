<?php

namespace App\Services;

use App\Services\Ocr\Engines\EngineResolver;
use App\Services\Ocr\Rasterizers\RasterizerResolver;
use RuntimeException;

class OcrService
{
    /**
     * Rasterize every page of the PDF at $absolutePdfPath (via whichever
     * rasterizer is available: mutool, Ghostscript, or Poppler) and run
     * OCR on each page (via whichever engine is available: Tesseract, or
     * the built-in Windows OCR as a zero-install fallback).
     *
     * @return array{text: string, pages: int, engine: string, rasterizer: string}
     */
    public static function extractText(string $absolutePdfPath): array
    {
        if (!is_file($absolutePdfPath)) {
            throw new RuntimeException("PDF not found: {$absolutePdfPath}");
        }

        $rasterizer = RasterizerResolver::resolve();
        $engine     = EngineResolver::resolve();

        $workDir = storage_path('app/ocr-tmp/' . uniqid('ocr_', true));
        if (!mkdir($workDir, 0777, true) && !is_dir($workDir)) {
            throw new RuntimeException("Could not create OCR work directory: {$workDir}");
        }

        try {
            $pageImages = $rasterizer->rasterize($absolutePdfPath, $workDir, config('ocr.dpi'));

            $maxPages = config('ocr.max_pages');
            if (count($pageImages) > $maxPages) {
                $pageImages = array_slice($pageImages, 0, $maxPages);
            }

            $text = '';
            foreach ($pageImages as $imagePath) {
                $text .= $engine->recognize($imagePath) . "\n\n";
            }

            $text = trim($text);
            $maxLen = config('ocr.max_text_length');
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

    private static function cleanup(string $dir): void
    {
        if (!is_dir($dir)) return;
        foreach (glob($dir . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($dir);
    }
}
