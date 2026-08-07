<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Bluebook;
use App\Models\Log;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        // Users
        $admin = User::create([
            'name'       => 'Jeliard D. Almonte',
            'email'      => 'jealmonte@cspc.edu.ph',
            'password'   => Hash::make('admin123'),
            'role'       => 'Admin',
            'can_upload' => true,
        ]);

        $student = User::create([
            'name'       => 'Gil Rexis E. Realubit',
            'email'      => 'bhokrealubit@my.cspc.edu.ph',
            'password'   => Hash::make('student123'),
            'role'       => 'Student',
            'can_upload' => false,
        ]);

        // Bluebooks
        $b1 = Bluebook::create([
            'title'            => 'Implementation of an Automated Student Attendance System Using RFID Technology',
            'authors'          => ['Dela Cruz, Maria Santos', 'Reyes, John Paul'],
            'year'             => 2024,
            'department'       => 'CCS',
            'program'          => 'Bachelor of Science in Information Technology',
            'keywords'         => ['RFID', 'Attendance System', 'Automation'],
            'abstract'         => 'This study presents the design and implementation of an automated student attendance monitoring system using Radio Frequency Identification (RFID) technology at CSPC.',
            'adviser'          => 'Prof. Ricardo Manalo',
            'status'           => 'Approved',
            'uploaded_by'      => $student->email,
            'uploaded_by_name' => $student->name,
            'pages'            => 124,
            'views'            => 45,
            'date_added'       => '2024-06-15',
        ]);

        $b2 = Bluebook::create([
            'title'            => 'Development of a Mobile-Based Farm Management System for Small-Scale Farmers',
            'authors'          => ['Santos, Ana Liza', 'Buenaventura, Carlo'],
            'year'             => 2024,
            'department'       => 'CCS',
            'program'          => 'Bachelor of Science in Computer Science',
            'keywords'         => ['Mobile Application', 'Farm Management', 'Agriculture'],
            'abstract'         => 'This capstone project developed a mobile application to assist small-scale farmers in managing their farm activities, inventory, and financial records.',
            'adviser'          => 'Prof. Elena Sorriso',
            'status'           => 'Approved',
            'uploaded_by'      => $student->email,
            'uploaded_by_name' => $student->name,
            'pages'            => 98,
            'views'            => 32,
            'date_added'       => '2024-05-20',
        ]);

        $b3 = Bluebook::create([
            'title'            => 'AI-Powered Crop Disease Detection Using Convolutional Neural Networks',
            'authors'          => ['Mendoza, Grace Anne', 'Corpuz, Ronald'],
            'year'             => 2023,
            'department'       => 'CCS',
            'program'          => 'Bachelor of Science in Computer Science',
            'keywords'         => ['AI', 'CNN', 'Crop Disease', 'Machine Learning'],
            'abstract'         => 'This research implements a convolutional neural network model to detect and classify crop diseases from plant leaf images captured via smartphone cameras.',
            'adviser'          => 'Prof. Marcelino Abuda',
            'status'           => 'Pending',
            'uploaded_by'      => $student->email,
            'uploaded_by_name' => $student->name,
            'pages'            => 156,
            'views'            => 0,
            'date_added'       => '2026-03-10',
        ]);

        $b4 = Bluebook::create([
            'title'            => 'Blockchain-Based Academic Credential Verification System',
            'authors'          => ['Torres, Rina Mae', 'Castillo, Danilo Jr.'],
            'year'             => 2023,
            'department'       => 'CCS',
            'program'          => 'Bachelor of Science in Information Technology',
            'keywords'         => ['Blockchain', 'Credential Verification', 'Security'],
            'abstract'         => 'This study explores the application of blockchain technology as a secure and tamper-proof mechanism for verifying academic credentials issued by CSPC.',
            'adviser'          => 'Prof. Felix Carandang',
            'status'           => 'Rejected',
            'uploaded_by'      => $student->email,
            'uploaded_by_name' => $student->name,
            'pages'            => 133,
            'views'            => 0,
            'date_added'       => '2026-03-08',
        ]);

        // Logs
        $logs = [
            [$student, 'Login',            '—',                                                  '2026-03-20 09:00:00'],
            [$student, 'Viewed Bluebook',  'RFID Automated Attendance System',                   '2026-03-20 09:23:14'],
            [$student, 'Uploaded Bluebook','AI-Powered Crop Disease Detection Using CNN',         '2026-03-20 10:15:00'],
            [$admin,   'Login',            '—',                                                  '2026-03-20 11:00:00'],
            [$admin,   'Approved Bluebook','RFID Automated Attendance System',                   '2026-03-20 11:05:00'],
            [$admin,   'Rejected Bluebook','Blockchain-Based Academic Credential Verification',  '2026-03-20 11:10:00'],
        ];

        foreach ($logs as [$user, $action, $document, $timestamp]) {
            Log::create([
                'user_name' => $user->name,
                'email'     => $user->email,
                'action'    => $action,
                'document'  => $document,
                'timestamp' => $timestamp,
                'status'    => 'Success',
            ]);
        }
    }
}
