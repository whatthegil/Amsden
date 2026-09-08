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

    // Poppler's text-layer extractor, used by App\Services\PdfTextExtractor for
    // the similarity checker's file-upload path (no-OCR fast path).
    'pdftotext_path'   => env('OCR_PDFTOTEXT_PATH'),   // Poppler

    // Tesseract language pack (must exist in tessdata/), e.g. "eng".
    'language' => env('OCR_LANGUAGE', 'eng'),

    // Rasterization resolution in DPI. Higher = more accurate, slower.
    'dpi' => (int) env('OCR_DPI', 300),

    // Safety cap on how many pages of a single document get OCR'd.
    'max_pages' => (int) env('OCR_MAX_PAGES', 60),

    // Page cap for the similarity checker's synchronous upload path. Lower than
    // max_pages because a web request cannot wait on a full-length OCR run;
    // a pre-proposal's title and abstract sit in its first few pages.
    'sync_max_pages' => (int) env('OCR_SYNC_MAX_PAGES', 12),

    // Per-process timeout in seconds (applies to each rasterizer/OCR invocation).
    'timeout' => (int) env('OCR_TIMEOUT', 120),

    // Wall-clock budget for one queued OCR job (seconds). A 60-page document
    // measures ~205s on a dev machine, so leave room for slower hosts.
    'job_timeout' => (int) env('OCR_JOB_TIMEOUT', 600),

    // A bluebook left in ocr_status='processing' for longer than this (seconds)
    // is treated as abandoned by a killed worker, so it can be reprocessed.
    // Must exceed tries x job_timeout (2 x 600) so a legitimate retry is not
    // mistaken for an abandoned run.
    'stuck_after' => (int) env('OCR_STUCK_AFTER', 1800),

    // Cap on stored extracted text length (characters) to bound DB row size.
    'max_text_length' => (int) env('OCR_MAX_TEXT_LENGTH', 500000),
];
