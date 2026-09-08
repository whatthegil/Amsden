<?php

/*
|--------------------------------------------------------------------------
| Departments / Colleges and their Programs
|--------------------------------------------------------------------------
|
| Keyed by the code stored on each bluebook (bluebooks.department). Each
| entry has a full display name and the list of degree programs offered.
| These drive every department picker/filter and the grouped Program
| dropdown on the upload and admin bluebook forms — add a program here and
| it shows up everywhere automatically.
|
*/

return [
    'CCS' => [
        'name' => 'College of Computer Studies',
        'programs' => [
            'Bachelor of Science in Information Technology',
            'Bachelor of Science in Computer Science',
            'Bachelor of Science in Information Systems',
            'Bachelor of Library and Information Science',
        ],
    ],
    'CEA' => [
        'name' => 'College of Engineering and Architecture',
        'programs' => [
            'Bachelor of Science in Civil Engineering',
            'Bachelor of Science in Electrical Engineering',
            'Bachelor of Science in Electronics Engineering',
            'Bachelor of Science in Mechanical Engineering',
            'Bachelor of Science in Architecture',
            'Bachelor of Science in Computer Engineering',
        ],
    ],
    'CAS' => [
        'name' => 'College of Arts and Sciences',
        'programs' => [
            'Bachelor of Science in Human Services',
            'Bachelor of Arts in English Language Studies',
            'Bachelor of Science in Development Communication',
            'Bachelor of Public Administration',
            'Bachelor of Science in Mathematics',
            'Bachelor of Science in Applied Mathematics',
        ],
    ],
    'CHS' => [
        'name' => 'College of Health Sciences',
        'programs' => [
            'Bachelor of Science in Nursing',
            'Bachelor of Science in Midwifery',
        ],
    ],
    'CTDE' => [
        'name' => 'College of Teacher Development and Education',
        'programs' => [
            'Bachelor of Technical-Vocational Teacher Education, Major in:',
            'Food Service Management',
            'Electronics Technology',
            'Fish Processing',
            'Bachelor of Special Needs Education',
            'Bachelor of Physical Education',
            'Bachelor of Culture and Arts Education',
        ],
    ],
    'CTHMBM' => [
        'name' => 'College of Tourism, Hospitality and Business Management',
        'programs' => [
            'Bachelor of Science in Office Administration',
            'Bachelor of Science in Hospitality Management',
            'Bachelor of Science in Entrepreneurship',
            'Bachelor of Science in Tourism Management',
            'Bachelor of Science in Business Administration, Major in Financial Management',
        ],
    ],
    'CSPC Buhi' => [
        'name' => 'CSPC Buhi',
        'programs' => [
            'Bachelor of Science in Office Administration',
            'Bachelor of Early Childhood Education',
        ],
    ],
];
