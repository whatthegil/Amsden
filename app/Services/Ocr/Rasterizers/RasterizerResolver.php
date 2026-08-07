<?php

namespace App\Services\Ocr\Rasterizers;

use RuntimeException;

class RasterizerResolver
{
    public static function resolve(): PdfRasterizer
    {
        $candidates = [
            new MuPdfRasterizer(),
            new GhostscriptRasterizer(),
            new PopplerRasterizer(),
        ];

        foreach ($candidates as $candidate) {
            if ($candidate->isAvailable()) return $candidate;
        }

        throw new RuntimeException(
            'No PDF rasterizer found. Install one of: mutool (MuPDF), Ghostscript, or Poppler (pdftoppm) — ' .
            'or point OCR_MUTOOL_PATH / OCR_GHOSTSCRIPT_PATH / OCR_PDFTOPPM_PATH at an existing install.'
        );
    }
}
