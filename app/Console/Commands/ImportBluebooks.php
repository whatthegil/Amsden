<?php

namespace App\Console\Commands;

use App\Models\Bluebook;
use App\Services\Store;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Imports the PDFs committed under Bluebooks/ into the archive.
 *
 * These documents ship with the repository, so they are already present on
 * any deployed environment. Uploading them through the web form instead would
 * mean pushing ~166 MB through the app over HTTP, against a 20 second request
 * timeout, one file at a time — this copies them straight onto the configured
 * bluebook disk and writes the matching rows.
 *
 * Metadata below was read from each document's own title page and abstract, not
 * invented. Advisers are not recorded on those pages, so they are left blank
 * for a librarian to fill in through Admin > Bluebooks > Edit.
 *
 * Safe to re-run: a document whose title is already in the archive is skipped.
 */
class ImportBluebooks extends Command
{
    protected $signature = 'bluebooks:import {--pretend : List what would be imported without writing anything}';

    protected $description = 'Import the bundled Bluebooks/*.pdf documents into the archive';

    /**
     * Keyed by a fragment that uniquely identifies the file on disk, because the
     * committed filenames are long and contain irregular spacing.
     */
    private function manifest(): array
    {
        return [
            'READINESS OF ACADEMIC LIBRARIES' => [
                'title'   => 'Readiness of Academic Libraries in Makerspace Implementation: Challenges and Benefits',
                'authors' => ['Tombado, Marygrace L.', 'Cariño, Maurine A.', 'Celaje, Jodie Bea B.', 'Bermido, Maria Cristina C.'],
                'year'    => 2025,
                'program' => 'Bachelor of Library and Information Science',
                'pages'   => 199,
                'keywords' => ['Makerspace', 'Academic Libraries', 'Rinconada Area', 'Libraries', 'Challenges', 'Benefits'],
                'abstract' => 'This study was conducted to assess the readiness of academic libraries in implementing makerspace and to identify the challenges and benefits encountered within the selected institutions in the Rinconada Area. Quantitative, specifically descriptive-correlational methods, were used to determine the relationship between the types of makerspace implemented and the challenges encountered by academic libraries. Constructivist Learning Theory and the Collaborative Learning Theory were used to analyze and explain how makerspaces enhance learning and collaboration in academic environments. Using a sampling technique, the researchers identified 30 qualified library personnel who were the target of the study and were currently employed in selected academic institutions in the Rinconada Area.',
            ],
            'TECHNOLOGY INTEGRATION IN PROMOTING' => [
                'title'   => 'Technology Integration in Promoting Reading Literacy in Iriga City Public Library',
                'authors' => ['Ya-on, Jhoemie A.', 'Jacob, Beverly G.', 'Sombrero, Ma. Therese T.'],
                'year'    => 2024,
                'program' => 'Bachelor of Library and Information Science',
                'pages'   => 119,
                'keywords' => ['Technologies', 'Technology Integration', 'Reading Literacy', 'Iriga City', 'Public Library'],
                'abstract' => 'The study explores integrating technology to enhance reading literacy at the Iriga City Public Library. It aims to identify specific technologies that can improve reading literacy among library patrons while also addressing the challenges of incorporating digital tools in a traditional library setting. The researchers employed a quantitative, descriptive design in gathering the data and used the Technology Acceptance Model to examine technology adoption and user behavior. The study involved 355 respondents, including students, professionals, parents, and others such as PWDs, pregnant women, senior citizens, and out-of-school youth, drawn from the library users\' monthly login records.',
            ],
            'RANDOM FOREST APPROACH' => [
                'title'   => 'A Random Forest Approach for Crop Fertilizer Recommendation Using NPK Sensor',
                'authors' => ['Bañaria, Joshua Charles B.', 'Belen, Laurence Bryan B.', 'Lagdaan, Jeremy C.'],
                'year'    => 2025,
                'program' => 'Bachelor of Science in Computer Science',
                'pages'   => 154,
                'keywords' => ['Random Forest', 'Fertilizer Recommendation System', 'NPK Sensor', 'Soil Nutrient Analysis'],
                'abstract' => 'This study addresses the limitations of conventional fertilizer recommendation systems, which often provide generalized advice that fails to consider soil nutrient variability and crop-specific requirements, resulting in inefficient fertilizer use and reduced crop yields. The objective of this research is to develop a fertilizer recommendation system that supports informed decision-making through real-time soil nutrient analysis and machine learning techniques. The system utilizes an NPK sensor to measure nitrogen, phosphorus, and potassium levels in the soil, interfaced with an Arduino microcontroller that collects and transmits nutrient data to a web-based application developed using the Flask framework.',
            ],
            'POTHOLE DETECTION' => [
                'title'   => 'Pothole Detection and Road Condition Reporting and Mapping with Image Processing Using YOLOv11',
                'authors' => ['Belano, Christine Kyla O.', 'De las Llagas, Gian B.', 'Nanale, Krizia Belle L.'],
                'year'    => 2025,
                'program' => 'Bachelor of Science in Computer Science',
                'pages'   => 165,
                'keywords' => ['Pothole Detection', 'YOLOv11 Object Detection', 'Road Condition Mapping', 'Edge Deployment', 'Mobile Inference'],
                'abstract' => 'Road infrastructure faced significant challenges, particularly in maintaining road quality and ensuring the safety of motorists and pedestrians. Traditional manual inspection methods of assessing road condition were time-consuming and often inefficient, particularly in rural areas where resources and manpower for infrastructure maintenance were limited. This study aimed to develop a real-time pothole detection and road condition mapping system using image processing and the YOLOv11 algorithm. A dataset of 856 road images manually annotated with potholes classified as minor, medium, and major was used, with data augmentation techniques applied to improve model robustness. The YOLOv11 model was trained using a staged transfer learning approach and evaluated using mAP and precision metrics.',
            ],
            'DIGITAL TRANSFORMATION OF KKBN' => [
                'title'   => 'Digital Transformation of KKBN Business Process Using Odoo: A Capstone Project',
                'authors' => ['Buergo, Gerald C.', 'Hontalba, John Edward M.', 'Magno, Sharrycel B.'],
                'year'    => 2025,
                'program' => 'Bachelor of Science in Information Systems',
                'pages'   => 193,
                'keywords' => ['Digital Transformation', 'Enterprise Resource Planning (ERP)', 'Odoo', 'ISO 25010', 'Small and Medium Enterprises (SMEs)', 'Business Operations'],
                'abstract' => 'In today\'s competitive environment, small and medium enterprises (SMEs) must embrace digital transformation to enhance operational efficiency and streamline processes. This study focused on implementing the Odoo Enterprise Resource Planning (ERP) system in Kayamanan, Kalusugan, Basta Natural (KKBN). The objectives include analyzing existing business processes, configuring key Odoo modules — Inventory, Sales, Accounting, Point of Sale, Purchase, and Employee Management with Attendances — and evaluating the system\'s acceptability based on ISO 25010 quality characteristics, including Functional Suitability, Performance Efficiency, Usability, Maintainability, Reliability, and Security.',
            ],
            'ROBOTIC PROCESS AUTOMATION' => [
                'title'   => 'Integration of Robotic Process Automation (RPA) 1.0 in the Academic Operational Task of the College of Computer Studies (CCS): A Module Automating the Procedure for Generation of Workload Schedule of Faculty',
                'authors' => ['Bermas, Niño Jaybee R.', 'Penolio, Ruby Mae O.', 'Saballero, Adrian G.'],
                'year'    => 2025,
                'program' => 'Bachelor of Science in Information Systems',
                'pages'   => 189,
                'keywords' => ['Automation', 'Software Robot', 'Workload Schedule', 'Manual Practices', 'Business Process Management'],
                'abstract' => 'Employees in modern workplaces increasingly rely on technology to manage operational processes, yet this requires proper planning and implementation to remain efficient. In the College of Computer Studies (CCS) at Camarines Sur Polytechnic Colleges, several long-standing procedures, particularly in the manual preparation of documents, have become repetitive and time-consuming. This study aims to enhance the generation of faculty workload schedules by assessing its automation potential and effectiveness using Robotic Process Automation.',
            ],
            'MULTI-BRANCH DENTAL PRACTICE' => [
                'title'   => 'Development of Multi-Branch Dental Practice Management System with Point-of-Sale and Business Analytics for Dr. Wennie A. Samper Dental Clinic',
                'authors' => ['Comploma, Janine R.', 'Sabroso, Alexander Edrian O.', 'Tucay, Ma. Crizel H.'],
                'year'    => 2025,
                'program' => 'Bachelor of Science in Information Technology',
                'pages'   => 218,
                'keywords' => ['Dental Practice Management', 'Multi-branch Systems', 'Point-of-Sale (POS)', 'Business Analytics', 'Electronic Medical Records', 'Healthcare Informatics', 'Clinic Workflow Automation'],
                'abstract' => 'This study presents the development of a comprehensive, web-based multi-branch dental practice management system designed to modernize the operations of Dr. Wennie A. Samper Dental Clinic by replacing inefficient, error-prone manual processes with an integrated digital solution. Developed using the Agile Scrum methodology, the system centralizes nine core modules including Electronic Medical Records, automated appointment scheduling, Point-of-Sale (POS) billing, and a business analytics dashboard to streamline cross-branch coordination and data-driven decision-making.',
            ],
            'LEVERAGING DATA ANALYTICS' => [
                'title'   => 'Leveraging Data Analytics for Intelligent Library Management of Camarines Sur Polytechnic Colleges',
                'authors' => ['Acbang, John Patrick A.', 'Deliva, Dince A.', 'Nazaria, Ma. Angelica T.'],
                'year'    => 2025,
                'program' => 'Bachelor of Science in Information Technology',
                'pages'   => 178,
                'keywords' => ['Intelligent Library Management System', 'Library Automation', 'Data Analytics', 'Collection Development'],
                'abstract' => 'This study developed the Intelligent Library Management System to improve library operations at the Camarines Sur Polytechnic Colleges Library by integrating book circulation, reporting, overdue risk assessment, SMS notifications, and analytics into a single digital platform. The system supports data-driven decision-making by providing insights into book usage and resource utilization, and was evaluated through unit, integration, usability, and performance testing.',
            ],
            'PETKHO PORTAL' => [
                'title'   => 'PetKho Portal: Smart Veterinary Care and Monitoring System for Kho Veterinary Clinic Rinconada',
                'authors' => ['Peregrino, Jey Kyle E.', 'Relatores, Mary Pons Rica E.', 'Urbina, Anne Psylocke S.'],
                'year'    => 2025,
                'program' => 'Bachelor of Science in Information Technology',
                'pages'   => 212,
                'keywords' => ['Local Based Veterinary System', 'Automation of Pet Medical Health Record', 'Invoice Automation'],
                'abstract' => 'The PetKho Portal is a smart online system created to help Kho Veterinary Clinic Rinconada improve its daily work and provide better service to pet owners. The clinic used to rely on handwritten records, walk-in appointments, and manual updates, which often caused delays, missing information, and extra workload for staff. The system includes online appointment scheduling, pet record management, inventory tracking, billing and payment recording, referral handling, and automatic notifications, and was developed using the Agile approach.',
            ],
            'TRACKCSPC' => [
                'title'   => 'TrackCSPC: A Smart Campus Vehicle Tracking and Reservation Platform',
                'authors' => ['Beriña, Raxine D.', 'Layron, Debie S.', 'Paat, Ronan Gabriel V.'],
                'year'    => 2025,
                'program' => 'Bachelor of Science in Information Technology',
                'pages'   => 197,
                'keywords' => ['GPS Tracking', 'Vehicle Reservation System', 'Campus Vehicle Tracking'],
                'abstract' => 'This study presents TrackCSPC, a smart campus vehicle tracking and reservation platform developed for the Camarines Sur Polytechnic Colleges General Services Unit (GSU) to address inefficiencies in manual vehicle reservation processes, including delays, inconsistent documentation, and limited monitoring. The system integrates a mobile application for submitting vehicle requests and tracking reservations in real time with a web-based administrative dashboard for approval, driver assignment, and GPS-based vehicle monitoring.',
            ],
        ];
    }

    public function handle(): int
    {
        $root = base_path('Bluebooks');

        if (!is_dir($root)) {
            $this->error("No Bluebooks directory at {$root}");
            return self::FAILURE;
        }

        $files = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root)) as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'pdf') {
                $files[] = $file->getPathname();
            }
        }

        $pretend  = (bool) $this->option('pretend');
        $disk     = Storage::disk(Store::bluebookDisk());
        $imported = 0;
        $skipped  = 0;

        $this->line('Disk: ' . Store::bluebookDisk() . ' | files found: ' . count($files));

        foreach ($this->manifest() as $needle => $meta) {
            $match = null;
            foreach ($files as $path) {
                if (stripos(basename($path), $needle) !== false) {
                    $match = $path;
                    break;
                }
            }

            if (!$match) {
                $this->warn("  no file matching \"{$needle}\"");
                $skipped++;
                continue;
            }

            if (Bluebook::where('title', $meta['title'])->exists()) {
                $this->line("  already in archive: {$meta['title']}");
                $skipped++;
                continue;
            }

            if ($pretend) {
                $this->info('  would import: ' . $meta['title']);
                $imported++;
                continue;
            }

            $stored = 'bluebooks/' . bin2hex(random_bytes(16)) . '.pdf';

            try {
                // Streamed rather than read into a string: these run to 29 MB and
                // the app container is small, so file_get_contents plus a string
                // upload would hold the whole document in memory twice over.
                $handle = fopen($match, 'rb');
                $disk->writeStream($stored, $handle);
                if (is_resource($handle)) {
                    fclose($handle);
                }
            } catch (\Throwable $e) {
                // Keep going: one unwritable document should not stop the rest,
                // and the run can simply be repeated once the cause is fixed.
                $this->error('  failed to store ' . basename($match) . ': ' . $e->getMessage());
                $skipped++;
                continue;
            }

            Bluebook::create([
                'title'              => $meta['title'],
                'authors'            => $meta['authors'],
                'year'               => $meta['year'],
                'department'         => 'CCS',
                'program'            => $meta['program'],
                'keywords'           => $meta['keywords'],
                'abstract'           => $meta['abstract'],
                'adviser'            => '',
                'status'             => 'Approved',
                'uploaded_by'        => 'library@cspc.edu.ph',
                'uploaded_by_name'   => 'CSPC Library',
                'pages'              => $meta['pages'],
                'views'              => 0,
                'date_added'         => now()->setTimezone('Asia/Manila')->format('Y-m-d'),
                'file_path'          => $stored,
                'file_original_name' => basename($match),
                'file_size'          => filesize($match),
            ]);

            $this->info('  imported: ' . $meta['title']);
            $imported++;
        }

        $this->newLine();
        $this->line($pretend ? "Would import {$imported}, skip {$skipped}." : "Imported {$imported}, skipped {$skipped}.");

        return self::SUCCESS;
    }
}
