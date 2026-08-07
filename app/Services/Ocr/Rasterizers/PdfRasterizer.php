<?php

namespace App\Services\Ocr\Rasterizers;

interface PdfRasterizer
{
    public function isAvailable(): bool;

    public function name(): string;

    /**
     * Render every page of $pdfPath to PNG files inside $outDir.
     *
     * @return string[] absolute PNG paths, sorted in page order
     */
    public function rasterize(string $pdfPath, string $outDir, int $dpi): array;
}
