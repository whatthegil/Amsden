<?php

namespace Tests\Unit;

use App\Services\BluebookDetails;
use Tests\Support\ThesisPdf;
use Tests\TestCase;

class BluebookDetailsTest extends TestCase
{
    public function test_every_detail_is_read_from_the_front_matter(): void
    {
        $d = BluebookDetails::fromText(ThesisPdf::text());

        $this->assertSame(ThesisPdf::TITLE, $d['title']);
        $this->assertSame(ThesisPdf::AUTHORS, $d['authors']);
        $this->assertSame(ThesisPdf::ADVISER, $d['adviser']);
        $this->assertSame(ThesisPdf::KEYWORDS, $d['keywords']);
        $this->assertSame(ThesisPdf::ABSTRACT, $d['abstract']);
        $this->assertSame('CCS', $d['department']);
        $this->assertSame('Bachelor of Science in Information Technology', $d['program']);
        $this->assertSame(120, $d['pages']);
    }

    public function test_the_page_count_comes_from_the_file_itself(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'thesis');
        file_put_contents($path, ThesisPdf::bytes());

        try {
            $this->assertSame(3, BluebookDetails::pageCount($path));
            $d = BluebookDetails::fromPdf($path);
        } finally {
            @unlink($path);
        }

        // The abstract page says 120; the file has 3, and the file wins.
        $this->assertSame(3, $d['pages']);
        $this->assertSame(ThesisPdf::TITLE, $d['title']);
    }

    public function test_the_program_is_matched_on_its_telling_words(): void
    {
        $d = BluebookDetails::fromText(ThesisPdf::text(ThesisPdf::pages(degree: 'Bachelor of Science in Information System')));
        $this->assertSame('Bachelor of Science in Information Systems', $d['program']);

        $d = BluebookDetails::fromText(ThesisPdf::text(ThesisPdf::pages(degree: 'BACHELOR OF SCIENCE IN COMPUTER ENGINEERING')));
        $this->assertSame('Bachelor of Science in Computer Engineering', $d['program']);
        // Named on the title page as CCS, but CCS has no such program.
        $this->assertSame('CEA', $d['department']);
    }

    public function test_a_program_two_colleges_offer_goes_to_the_one_named(): void
    {
        $d = BluebookDetails::fromText(ThesisPdf::text(ThesisPdf::pages('CSPC Buhi', 'Bachelor of Science in Office Administration')));

        $this->assertSame('CSPC Buhi', $d['department']);
        $this->assertSame('Bachelor of Science in Office Administration', $d['program']);
    }

    public function test_the_subject_adviser_is_not_taken_for_the_papers_adviser(): void
    {
        $pages = ThesisPdf::pages();
        $pages[1] = ['ACKNOWLEDGMENTS', 'To Mr. Carlo D. Mendoza, our capstone subject adviser, for his patience.'];

        $this->assertSame('', BluebookDetails::fromText(ThesisPdf::text($pages))['adviser']);

        $pages[1][] = 'We thank our thesis adviser, Mrs. Rosel O. Onesa, for her support.';
        $this->assertSame('Onesa, Rosel O.', BluebookDetails::fromText(ThesisPdf::text($pages))['adviser']);
    }

    public function test_names_are_filed_last_name_first(): void
    {
        $this->assertSame('Dela Cruz, Juan P.', BluebookDetails::personName('Juan P. Dela Cruz'));
        $this->assertSame('Sombrero, Ma. Therese T.', BluebookDetails::personName('Ma. Therese T. Sombrero'));
        $this->assertSame('Belano, Christine Kyla O.', BluebookDetails::personName('Belano, Christine Kyla O.'));
        $this->assertSame('Ibo Jr., Allan O.', BluebookDetails::personName('Mr. Allan O. Ibo, Jr.'));
        $this->assertSame('Manlapaz, Shiela Dona S.', BluebookDetails::personName('Shiela Dona S. Manlapaz, MIT'));
        $this->assertSame('Paat, Ronan Gabriel', BluebookDetails::personName('Ronan Gabriel Paat'));
    }

    public function test_a_shouted_title_is_put_in_title_case_keeping_the_abstracts_spellings(): void
    {
        $pages = ThesisPdf::pages();
        $pages[0][0] = 'POTHOLE DETECTION USING YOLOV11 FOR DR. JUAN A. CRUZ:';
        $pages[0][1] = 'A STUDY OF ODOO';
        $pages[2][3] = 'Pothole Detection using YOLOv11 for Dr. Juan A. Cruz:';
        $pages[2][4] = 'A Study of Odoo';

        $this->assertSame('Pothole Detection using YOLOv11 for Dr. Juan A. Cruz: A Study of Odoo', BluebookDetails::fromText(ThesisPdf::text($pages))['title']);
    }

    public function test_a_document_with_no_text_yields_nothing_rather_than_failing(): void
    {
        $d = BluebookDetails::fromText('');

        $this->assertSame('', $d['title']);
        $this->assertSame([], $d['authors']);
        $this->assertSame(['title', 'authors', 'adviser', 'keywords', 'abstract', 'college', 'program', 'number of pages'], BluebookDetails::missing($d));
    }
}
