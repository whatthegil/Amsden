<?php

namespace Tests\Unit;

use App\Services\Citation\PersonName;
use App\Services\Citation\TitleCase;
use Tests\TestCase;

/**
 * The two things every citation style gets wrong first: where a Filipino
 * surname starts, and which words of a title keep their capitals.
 */
class CitationNamesAndTitlesTest extends TestCase
{
    public function test_surname_particles_stay_with_the_surname(): void
    {
        $this->assertSame(['given' => 'Maria A.', 'surname' => 'Dela Cruz', 'suffix' => ''], PersonName::parse('Maria A. Dela Cruz'));
        $this->assertSame('de los Santos', PersonName::parse('Juan de los Santos')['surname']);
        $this->assertSame('San Jose', PersonName::parse('Pedro San Jose')['surname']);
        $this->assertSame('Reyes', PersonName::parse('Ana Reyes')['surname']);
    }

    public function test_suffixes_are_kept_apart_from_the_surname(): void
    {
        $this->assertSame(['given' => 'Juan', 'surname' => 'Dela Cruz', 'suffix' => 'Jr.'], PersonName::parse('Juan Dela Cruz Jr.'));
        $this->assertSame(['given' => 'Juan A.', 'surname' => 'Dela Cruz', 'suffix' => 'Jr.'], PersonName::parse('Dela Cruz, Juan A., Jr.'));
        $this->assertSame('III', PersonName::parse('Pedro San Jose iii')['suffix']);
        // A middle initial V. is an initial, not "the fifth".
        $this->assertSame(['given' => 'Ronan V.', 'surname' => 'Paat', 'suffix' => ''], PersonName::parse('Paat, Ronan V.'));
    }

    public function test_apa_titles_are_in_sentence_case_with_names_kept(): void
    {
        $this->assertSame(
            'Readiness of academic libraries in makerspace implementation: Challenges and benefits',
            TitleCase::sentence('Readiness of Academic Libraries in Makerspace Implementation: Challenges and Benefits')
        );
        $this->assertSame(
            'Technology integration in promoting reading literacy in Iriga City Public Library',
            TitleCase::sentence('Technology Integration in Promoting Reading Literacy in Iriga City Public Library')
        );
        // Acronyms, inner capitals and digits keep their case without being listed.
        $this->assertSame('PetKho Portal: Smart care using YOLOv11 and NPK', TitleCase::sentence('PetKho Portal: Smart Care Using YOLOv11 and NPK'));
        $this->assertSame('A multi-branch point-of-sale system', TitleCase::sentence('A Multi-Branch Point-of-Sale System'));
    }

    public function test_titles_typed_in_capitals_are_recased(): void
    {
        $this->assertSame('An RFID attendance system for Camarines Sur Polytechnic Colleges',
            TitleCase::sentence('AN RFID ATTENDANCE SYSTEM FOR CAMARINES SUR POLYTECHNIC COLLEGES'));
        $this->assertSame('An RFID Attendance System for Camarines Sur Polytechnic Colleges',
            TitleCase::title('AN RFID ATTENDANCE SYSTEM FOR CAMARINES SUR POLYTECHNIC COLLEGES'));
    }
}
