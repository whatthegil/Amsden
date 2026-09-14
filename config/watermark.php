<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Watermarking the document itself
    |--------------------------------------------------------------------------
    |
    | The viewer draws the reader's identity into every page it renders, which
    | survives a screenshot but does nothing for the file: anyone fetching the
    | document route got the stored PDF unmarked. These two settings write the
    | mark into the PDF instead, and they answer different questions.
    |
    | "stored" stamps the file once at upload with where the document came
    | from, so any copy of it is identifiably CSPC's. "per_viewer" stamps what
    | is served, per request, with who asked for it, so a leaked file names the
    | account that fetched it.
    |
    | Both need a MuPDF binary on the host (see ocr.mutool_path - it is the same
    | one the rasterizer uses). Without it a document is served unstamped rather
    | than not at all: this is a protection, and losing it must not lose the
    | archive with it. Run `php artisan bluebooks:watermark --dry-run` to see
    | what the host can actually do.
    |
    */

    'stored'     => (bool) env('WATERMARK_STORED', true),

    /*
    | Costs a stamping pass per view - well under a second on a 180-page
    | document, but it is CPU per read and it needs the document to travel
    | through PHP. It is therefore bypassed entirely when
    | BLUEBOOK_DIRECT_FETCH is on, since a signed link serves the stored
    | object: that copy carries the provenance mark above and nothing else.
    */
    'per_viewer' => (bool) env('WATERMARK_PER_VIEWER', true),

    /*
    |--------------------------------------------------------------------------
    | Appearance
    |--------------------------------------------------------------------------
    |
    | Light enough to read the thesis through, dark enough to survive the
    | contrast knocked out of a photographed screen.
    |
    */

    'opacity' => (float) env('WATERMARK_OPACITY', 0.13),
    'size'    => (float) env('WATERMARK_SIZE', 11),

    /*
    | Seconds before a stamping run is abandoned. Documents here reach 200 pages
    | and 30 MB; a run that has not finished by this point is stuck, and the
    | reader is better served the stored file than a spinner.
    */

    'timeout' => (int) env('WATERMARK_TIMEOUT', 120),

];
