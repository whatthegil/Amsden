<?php

/*
|--------------------------------------------------------------------------
| Daily limits
|--------------------------------------------------------------------------
|
| How many times each account may use a tool per day (Philippine time). The
| literature review can make a paid OpenAI call on every search; the
| similarity check runs on the server, where an uploaded PDF can mean a
| minute of OCR.
|
*/

return [
    'daily' => [
        'literature_review' => (int) env('LITERATURE_REVIEW_DAILY_LIMIT', 5),
        'similarity_check'  => (int) env('SIMILARITY_CHECK_DAILY_LIMIT', 5),
    ],

    'timezone' => 'Asia/Manila',
];
