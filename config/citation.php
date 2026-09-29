<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Where the theses were written
    |--------------------------------------------------------------------------
    |
    | ACM cites a thesis with the school's city and country after its name.
    |
    */

    'school'   => env('CITATION_SCHOOL', 'Camarines Sur Polytechnic Colleges'),
    'location' => env('CITATION_LOCATION', 'Nabua, Camarines Sur, Philippines'),

    /*
    |--------------------------------------------------------------------------
    | Names that keep their capitals in sentence case
    |--------------------------------------------------------------------------
    |
    | APA puts a thesis title in sentence case but leaves proper nouns alone,
    | and no rule can tell "Library" in a library's name from "library" in
    | "library management". Acronyms, words with inner capitals or digits
    | (KKBN, YOLOv11, PetKho) are kept without being listed; the names below
    | are the rest. Matched as whole phrases, ignoring case. Add to it when a
    | new title names a place, an organisation or a product.
    |
    */

    'proper_nouns' => [
        'Camarines Sur Polytechnic Colleges',
        'Camarines Sur',
        'College of Computer Studies',
        'Iriga City Public Library',
        'Iriga City',
        'Rinconada',
        'Nabua',
        'Philippines',
        'Odoo',
        'Kho Veterinary Clinic Rinconada',
        'PetKho Portal',
        'Dr. Wennie A. Samper Dental Clinic',
        'Flask',
        'Arduino',

        // Acronyms, for titles typed in capitals: there the case says nothing,
        // so these are the only ones that come back.
        'CSPC', 'CCS', 'RFID', 'GPS', 'SMS', 'IoT', 'AI', 'ERP', 'POS', 'QR',
        'API', 'LMS', 'NPK', 'CNN', 'OCR', 'ISO', 'SMEs', 'RPA', 'KKBN', 'YOLOv11',
    ],

];
