<?php

namespace Tests\Feature;

use App\Models\Bluebook;
use App\Models\User;
use App\Services\BluebookEvaluation;
use App\Services\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The evaluation checklist, read from the OCR text and commented on by the admin. */
class EvaluationChecklistTest extends TestCase
{
    use RefreshDatabase;

    private const AUTHOR = 's@my.cspc.edu.ph';

    private const TEXT = <<<TXT
        Automated Attendance Monitoring Using RFID
        Maria Dela Cruz
        APPROVAL SHEET  This thesis is recommended for approval.
        CERTIFICATION  We certify that this thesis was read.
        ABSTRACT  An RFID attendance monitoring system.
        TABLE OF CONTENTS
        LIST OF TABLES  Table 1  Table 2  Table 4
        CHAPTER 1 Introduction  CHAPTER 2 Review  CHAPTER 3 Methods  CHAPTER 5 Conclusions
        Figure 1 shows the system.
        REFERENCES
        CURRICULUM VITAE  Contact number 0917 123 4567
        TXT;

    private function paper(array $overrides = []): Bluebook
    {
        return Bluebook::create(array_merge([
            'title' => 'Automated Attendance Monitoring Using RFID', 'authors' => ['Dela Cruz, Maria'], 'year' => 2025,
            'department' => 'CCS', 'program' => 'BSIT', 'keywords' => ['rfid'], 'abstract' => 'An abstract.', 'adviser' => 'Dr. A',
            'status' => 'Pending', 'pages' => 10, 'uploaded_by' => self::AUTHOR, 'uploaded_by_name' => 'Maria',
            'date_added' => '2026-01-01', 'ocr_status' => 'completed', 'ocr_engine' => 'text layer', 'ocr_text' => self::TEXT,
            'pdf_encrypted' => false,
        ], $overrides));
    }

    private function admin(): array
    {
        $u = User::create(['name' => 'Adm', 'email' => 'adm@cspc.edu.ph', 'password' => bcrypt('x'), 'role' => 'Admin']);

        return ['user' => ['id' => $u->id, 'name' => 'Adm', 'email' => 'adm@cspc.edu.ph', 'role' => 'Admin', 'canUpload' => false]];
    }

    private function author(): array
    {
        $u = User::firstOrCreate(['email' => self::AUTHOR], ['name' => 'Maria', 'password' => bcrypt('x'), 'role' => 'Student']);

        return ['user' => ['id' => $u->id, 'name' => 'Maria', 'email' => self::AUTHOR, 'role' => 'Student', 'canUpload' => true]];
    }

    public function test_the_checklist_is_read_from_the_ocr_text(): void
    {
        $auto = BluebookEvaluation::auto(Store::bookToArray($this->paper()));

        $this->assertSame('ok', $auto['approved_unit']['status']);
        $this->assertSame('ok', $auto['approval_pages']['status']);
        $this->assertSame('ok', $auto['toc']['status']);
        $this->assertSame('issue', $auto['lists']['status']);          // Figure 1, but no List of Figures
        $this->assertStringContainsString('List of Figures', $auto['lists']['note']);
        $this->assertSame('issue', $auto['labels']['status']);         // Table 3 skipped
        $this->assertStringContainsString('Table 3', $auto['labels']['note']);
        $this->assertSame('issue', $auto['complete']['status']);       // Chapter 4 missing
        $this->assertStringContainsString('Chapter 4', $auto['complete']['note']);
        $this->assertSame('ok', $auto['metadata']['status']);
        $this->assertSame('issue', $auto['sensitive']['status']);      // CV, no withheld pages
        $this->assertSame('ok', $auto['unencrypted']['status']);
        $this->assertSame('unchecked', $auto['layout']['status']);
    }

    public function test_withheld_pages_settle_the_sensitive_information_item(): void
    {
        $auto = BluebookEvaluation::auto(Store::bookToArray($this->paper(['withheld_pages' => '40-42'])));

        $this->assertSame('ok', $auto['sensitive']['status']);
    }

    public function test_nothing_is_judged_from_text_not_yet_read(): void
    {
        $auto = BluebookEvaluation::auto(Store::bookToArray($this->paper(['ocr_status' => 'pending', 'ocr_text' => null, 'pdf_encrypted' => null])));

        $this->assertSame('unchecked', $auto['approved_unit']['status']);
        $this->assertSame('Waiting for the text to be read.', $auto['approved_unit']['note']);
        $this->assertSame('unchecked', $auto['unencrypted']['status']);
    }

    public function test_the_admin_page_shows_the_checklist_pre_filled(): void
    {
        $b = $this->paper();

        $this->withSession($this->admin())->get("/admin/bluebooks/{$b->id}")
            ->assertOk()
            ->assertSee('Auto: not okay')
            ->assertSee('The numbering skips Table 3.')
            ->assertSee('name="status[labels]" value="issue" checked', false)
            ->assertSee(route('admin.bluebooks.evaluate', $b->id), false);
    }

    public function test_comments_are_kept_only_on_items_not_okay_and_the_author_sees_them(): void
    {
        $b = $this->paper();

        $this->withSession($this->admin())->post("/admin/bluebooks/{$b->id}/evaluate", [
            'status'  => ['toc' => 'ok', 'complete' => 'issue', 'labels' => 'issue'],
            'comment' => ['toc' => 'Should not be kept', 'complete' => 'Chapter 4 is missing, please add it.', 'labels' => ''],
        ])->assertRedirect(route('admin.bluebooks.view', $b->id));

        $saved = $b->fresh()->evaluation;
        $this->assertSame(['status' => 'ok'], $saved['toc']);
        $this->assertSame('Chapter 4 is missing, please add it.', $saved['complete']['comment']);
        $this->assertNull($saved['labels']['comment']);
        $this->assertNotNull($b->fresh()->evaluated_at);

        $this->withSession($this->author())->get('/student/my-uploads')
            ->assertOk()
            ->assertSee('found 2 problems')
            ->assertSee('Chapter 4 is missing, please add it.')
            ->assertSee(BluebookEvaluation::CRITERIA['labels'])
            ->assertDontSee('Should not be kept')
            ->assertDontSee(BluebookEvaluation::CRITERIA['toc']);
    }

    public function test_a_posted_bluebook_cannot_be_evaluated(): void
    {
        $b = $this->paper(['status' => 'Approved']);

        $this->withSession($this->admin())->post("/admin/bluebooks/{$b->id}/evaluate", ['status' => ['toc' => 'issue']]);

        $this->assertNull($b->fresh()->evaluation);
    }

    public function test_a_replaced_file_is_evaluated_afresh(): void
    {
        $b = $this->paper(['evaluation' => ['toc' => ['status' => 'issue', 'comment' => 'x']], 'evaluated_at' => now()]);

        Store::updateBluebook($b->id, ['filePath' => 'bluebooks/new.pdf']);

        $this->assertNull($b->fresh()->evaluation);
        $this->assertNull($b->fresh()->evaluated_at);
    }

    /** The same paper with its page breaks, as the text is saved now. */
    private const PAGED = [
        "Automated Attendance Monitoring Using RFID\nMaria Dela Cruz",                       // 1
        "APPROVAL SHEET  This thesis is recommended for approval.",                          // 2
        "CERTIFICATION  We certify that this thesis was read.",                              // 3
        "TABLE OF CONTENTS  Chapter 1 ... 5  Chapter 2 ... 6\nLIST OF TABLES  Table 1  Table 2  Table 4", // 4
        "CHAPTER 1 Introduction  Table 1 lists the users.",                                  // 5
        "CHAPTER 2 Review  Table 2 compares systems.",                                       // 6
        "CHAPTER 3 Methods  Table 4 shows the results.  Figure 1 shows the system.",         // 7
        "CHAPTER 5 Conclusions",                                                             // 8
        "REFERENCES",                                                                        // 9
        "CURRICULUM VITAE  Contact number 0917 123 4567",                                    // 10
    ];

    public function test_each_item_points_to_the_page_to_look_at(): void
    {
        $spots = BluebookEvaluation::spots(Store::bookToArray($this->paper(['ocr_text' => implode("\f", self::PAGED)])));
        $pages = fn(string $key) => array_column($spots[$key], 'page', 'label');

        $this->assertSame(['Approval sheet' => 2, 'Certification' => 3], $pages('approval_pages'));
        $this->assertSame(['Table of Contents' => 4], $pages('toc'));
        // Table 3 is skipped: the pages of Table 2 and Table 4 in the body, not the list of tables.
        $this->assertSame(['Table 2 (before Table 3)' => 6, 'Table 4 (before Table 3)' => 7], $pages('labels'));
        // Chapter 4 is missing: it belongs between Chapter 3 and Chapter 5.
        $this->assertSame(7, $pages('complete')['Chapter 3']);
        $this->assertSame(8, $pages('complete')['Chapter 5']);
        $this->assertSame(['Curriculum vitae' => 10], $pages('sensitive'));   // each page once
        $this->assertSame(['Title page' => 1, 'Chapter 1' => 5], $pages('format'));
        $this->assertArrayNotHasKey('unencrypted', $spots);
    }

    public function test_a_missing_part_points_to_where_it_belongs(): void
    {
        $text  = implode("\f", ['A Title', 'TABLE OF CONTENTS', 'CHAPTER 1 Intro', 'CHAPTER 2 End', 'Thank you']);
        $spots = BluebookEvaluation::spots(Store::bookToArray($this->paper(['ocr_text' => $text])));

        // No approval sheet anywhere: the title page and the contents, which would list it.
        $this->assertSame([1, 2], array_column($spots['approved_unit'], 'page'));
        // No references: the last page, where they would be.
        $this->assertSame(['page' => 5, 'label' => 'Last page'], end($spots['complete']));
    }

    public function test_text_without_page_breaks_offers_no_pages(): void
    {
        $this->assertSame([], BluebookEvaluation::spots(Store::bookToArray($this->paper())));
    }

    public function test_the_admin_page_links_items_to_their_pages(): void
    {
        $paged   = $this->paper(['ocr_text' => implode("\f", self::PAGED), 'file_path' => 'bluebooks/x.pdf']);
        $unpaged = $this->paper(['file_path' => 'bluebooks/y.pdf']);
        $admin   = $this->admin();

        $this->withSession($admin)->get("/admin/bluebooks/{$paged->id}")
            ->assertOk()
            ->assertSee('data-goto-page="2"', false)
            ->assertSee('Approval sheet &middot; p.&nbsp;2', false)
            ->assertDontSee('read before pages were tracked');

        $this->withSession($admin)->get("/admin/bluebooks/{$unpaged->id}")
            ->assertOk()
            ->assertDontSee('data-goto-page="', false)
            ->assertSee('read before pages were tracked');
    }

    public function test_watermarks_are_stripped_without_losing_page_breaks(): void
    {
        $text = "Page one CSPC ARCHIVE · NOT FOR REDISTRIBUTION\fPage two";

        $this->assertSame("Page one\fPage two", \App\Services\Pdf\PdfWatermarker::stripMarks($text));
    }

    public function test_an_encrypted_pdf_is_detected(): void
    {
        $plain = tempnam(sys_get_temp_dir(), 'pdf');
        $enc   = tempnam(sys_get_temp_dir(), 'pdf');
        file_put_contents($plain, "%PDF-1.7\n1 0 obj << >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF");
        file_put_contents($enc, "%PDF-1.7\n1 0 obj << >> endobj\ntrailer << /Root 1 0 R /Encrypt 2 0 R >>\n%%EOF");

        try {
            $this->assertFalse(BluebookEvaluation::isEncrypted($plain));
            $this->assertTrue(BluebookEvaluation::isEncrypted($enc));
        } finally {
            @unlink($plain);
            @unlink($enc);
        }
    }
}
