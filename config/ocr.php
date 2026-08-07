<?php

return [

    // All *_path settings below are optional pins. Leave blank to auto-detect:
    // each is looked up on PATH, then in common install locations, in that order.

    // OCR recognition engines, tried in order until one is available:
    'tesseract_path' => env('OCR_TESSERACT_PATH'), // Tesseract-OCR
    // (Windows' built-in OCR is used automatically as a no-install fallback
    // if Tesseract isn't found — see App\Services\Ocr\Engines\WindowsOcrEngine.)

    // PDF-to-image rasterizers, tried in order until one is available:
    'mutool_path'      => env('OCR_MUTOOL_PATH'),      // MuPDF
    'ghostscript_path' => env('OCR_GHOSTSCRIPT_PATH'), // Ghostscript
    'pdftoppm_path'    => env('OCR_PDFTOPPM_PATH'),    // Poppler

    // Tesseract language pack (must exist in tessdata/), e.g. "eng".
    'language' => env('OCR_LANGUAGE', 'eng'),

    // Rasterization resolution in DPI. Higher = more accurate, slower.
    'dpi' => (int) env('OCR_DPI', 300),

    // Safety cap on how many pages of a single document get OCR'd.
    'max_pages' => (int) env('OCR_MAX_PAGES', 60),

    // Per-process timeout in seconds (applies to each rasterizer/OCR invocation).
    'timeout' => (int) env('OCR_TIMEOUT', 120),

    // Cap on stored extracted text length (characters) to bound DB row size.
    'max_text_length' => (int) env('OCR_MAX_TEXT_LENGTH', 500000),
];
