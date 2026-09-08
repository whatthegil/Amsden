<?php

namespace App\Services\Ocr\Rasterizers;

interface PdfRasterizer
{
    public function isAvailable(): bool;

    public function name(): string;

    /**
     * Render pages of $pdfPath to PNG files inside $outDir.
     *
     * $maxPages caps the render at the first N pages. It must be pushed down
     * into the underlying tool rather than applied to the returned list —
     * rendering a 200-page bluebook at 300 DPI costs ~90s and ~300MB, so
     * rendering pages that are about to be discarded is what used to blow
     * OCR_TIMEOUT. Null renders every page.
     *
     * @return string[] absolute PNG paths, sorted in page order
     */
    public function rasterize(string $pdfPath, string $outDir, int $dpi, ?int $maxPages = null): array;
}
