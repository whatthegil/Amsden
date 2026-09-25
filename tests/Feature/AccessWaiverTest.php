<?php

namespace Tests\Feature;

use App\Models\Bluebook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The access permission waiver: the author chooses it on the upload form, and
 * the admin records it on the edit form once it matches the signed waiver.
 */
class AccessWaiverTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): array
    {
        return ['id' => 1, 'name' => 'Adm', 'email' => 'adm@cspc.edu.ph', 'role' => 'Admin', 'canUpload' => true];
    }

    private function uploader(): array
    {
        return ['id' => 2, 'name' => 'S T', 'email' => 's@my.cspc.edu.ph', 'role' => 'Student', 'canUpload' => true];
    }

    private function makeBluebook(): Bluebook
    {
        return Bluebook::create([
            'title'            => 'Waiver Paper',
            'authors'          => ['Dela Cruz, Maria'],
            'year'             => 2024,
            'department'       => 'CCS',
            'program'          => 'Bachelor of Science in Information Technology',
            'keywords'         => ['sample'],
            'abstract'         => 'A sample abstract.',
            'adviser'          => 'Dr. Adviser',
            'status'           => 'Pending',
            'pages'            => 60,
            'uploaded_by'      => 's@my.cspc.edu.ph',
            'uploaded_by_name' => 'S T',
            'date_added'       => '2026-01-01',
        ]);
    }

    private function editPayload(array $overrides = []): array
    {
        return array_merge([
            'title'      => 'Waiver Paper',
            'authors'    => 'Dela Cruz, Maria',
            'year'       => 2024,
            'department' => 'CCS',
            'program'    => 'Bachelor of Science in Information Technology',
            'keywords'   => 'sample',
            'abstract'   => 'A sample abstract.',
            'adviser'    => 'Dr. Adviser',
            'pages'      => 60,
        ], $overrides);
    }

    private function uploadPayload(array $overrides = []): array
    {
        return $this->editPayload([
            'title' => 'Uploaded Waiver Paper',
            'file'  => UploadedFile::fake()->createWithContent('w.pdf', "%PDF-1.4
%%EOF"),
        ] + $overrides);
    }

    public function test_upload_form_asks_for_the_waiver(): void
    {
        $res = $this->withSession(['user' => $this->uploader()])->get('/student/upload');

        $res->assertOk();
        $res->assertSee('Access Permission Waiver');
        foreach (Bluebook::ACCESS_LEVELS as $label) {
            $res->assertSee($label);
        }
        foreach (Bluebook::ACCESS_PARTS as $label) {
            $res->assertSee($label);
        }
    }

    public function test_upload_saves_the_authors_choice_without_recording_it(): void
    {
        Storage::fake('local');
        Queue::fake();

        $this->withSession(['user' => $this->uploader()])
            ->post('/student/upload', $this->uploadPayload([
                'access_level' => Bluebook::ACCESS_PARTIAL,
                'access_parts' => ['preliminary', 'abstract'],
                'part_from'    => ['preliminary' => 1, 'abstract' => 6],
                'part_to'      => ['preliminary' => 5, 'abstract' => 6],
            ]))
            ->assertOk()
            ->assertSee('pending admin approval');

        $bluebook = Bluebook::where('title', 'Uploaded Waiver Paper')->firstOrFail();
        $this->assertSame(Bluebook::ACCESS_PARTIAL, $bluebook->access_level);
        $this->assertSame(['preliminary' => ['from' => 1, 'to' => 5], 'abstract' => ['from' => 6, 'to' => 6]], $bluebook->access_parts);
        // Still the admin's to confirm against the signed form before posting.
        $this->assertNull($bluebook->waiver_recorded_at);
    }

    public function test_upload_without_a_waiver_choice_is_rejected(): void
    {
        Storage::fake('local');
        Queue::fake();

        $this->withSession(['user' => $this->uploader()])
            ->post('/student/upload', $this->uploadPayload())
            ->assertOk()
            ->assertSee('Please choose an access permission waiver.');

        $this->assertDatabaseMissing('bluebooks', ['title' => 'Uploaded Waiver Paper']);
    }

    public function test_upload_with_a_part_but_no_page_range_is_rejected(): void
    {
        Storage::fake('local');
        Queue::fake();

        $this->withSession(['user' => $this->uploader()])
            ->post('/student/upload', $this->uploadPayload([
                'access_level' => Bluebook::ACCESS_PARTIAL,
                'access_parts' => ['chapter1'],
            ]))
            ->assertOk()
            ->assertSee('Please enter the page range for Chapter 1.');

        $this->assertDatabaseMissing('bluebooks', ['title' => 'Uploaded Waiver Paper']);
    }

    public function test_admin_edit_form_prefills_the_authors_unrecorded_choice(): void
    {
        $bluebook = $this->makeBluebook();
        $bluebook->update(['access_level' => Bluebook::ACCESS_PARTIAL, 'access_parts' => ['abstract' => ['from' => 3, 'to' => 4]], 'waiver_requested_at' => now()]);

        $res = $this->withSession(['user' => $this->admin()])->get("/admin/bluebooks/{$bluebook->id}/edit");

        $res->assertOk();
        $res->assertSee('Not recorded yet');
        $this->assertMatchesRegularExpression('/value="partial" required\s+checked/', $res->getContent());
        $this->assertMatchesRegularExpression('/value="abstract"\s+checked/', $res->getContent());
        $res->assertSee('name="part_from[abstract]" placeholder="From" value="3"', false);
    }

    public function test_admin_edit_form_shows_the_waiver_with_its_saved_choice(): void
    {
        $bluebook = $this->makeBluebook();
        $bluebook->update(['access_level' => Bluebook::ACCESS_CONSULTATION, 'waiver_recorded_at' => now()]);

        $res = $this->withSession(['user' => $this->admin()])->get("/admin/bluebooks/{$bluebook->id}/edit");

        $res->assertOk();
        $res->assertSee('Access Permission Waiver');
        $this->assertMatchesRegularExpression('/value="consultation" required\s+checked/', $res->getContent());
        $this->assertDoesNotMatchRegularExpression('/value="public" required\s+checked/', $res->getContent());
    }

    public function test_admin_add_form_does_not_show_the_waiver(): void
    {
        $this->withSession(['user' => $this->admin()])
            ->get('/admin/bluebooks/new')
            ->assertOk()
            ->assertDontSee('Access Permission Waiver');
    }

    public function test_admin_can_set_a_partial_waiver(): void
    {
        $bluebook = $this->makeBluebook();

        $this->withSession(['user' => $this->admin()])
            ->post("/admin/bluebooks/{$bluebook->id}/edit", $this->editPayload([
                'access_level' => Bluebook::ACCESS_PARTIAL,
                'access_parts' => ['abstract', 'chapter1'],
                'part_from'    => ['abstract' => 3, 'chapter1' => 5],
                'part_to'      => ['abstract' => 4, 'chapter1' => 20],
            ]))
            ->assertRedirect(route('admin.bluebooks'));

        $bluebook->refresh();
        $this->assertSame(Bluebook::ACCESS_PARTIAL, $bluebook->access_level);
        $this->assertSame(['abstract' => ['from' => 3, 'to' => 4], 'chapter1' => ['from' => 5, 'to' => 20]], $bluebook->access_parts);
    }

    public function test_switching_away_from_partial_clears_the_parts(): void
    {
        $bluebook = $this->makeBluebook();
        $bluebook->update(['access_level' => Bluebook::ACCESS_PARTIAL, 'access_parts' => ['abstract' => ['from' => 3, 'to' => 4]], 'waiver_requested_at' => now()]);

        $this->withSession(['user' => $this->admin()])
            ->post("/admin/bluebooks/{$bluebook->id}/edit", $this->editPayload(['access_level' => Bluebook::ACCESS_PUBLIC]));

        $bluebook->refresh();
        $this->assertSame(Bluebook::ACCESS_PUBLIC, $bluebook->access_level);
        $this->assertNull($bluebook->access_parts);
    }

    public function test_page_range_beyond_the_document_is_rejected(): void
    {
        $bluebook = $this->makeBluebook();

        $this->withSession(['user' => $this->admin()])
            ->from("/admin/bluebooks/{$bluebook->id}/edit")
            ->post("/admin/bluebooks/{$bluebook->id}/edit", $this->editPayload([
                'access_level' => Bluebook::ACCESS_PARTIAL,
                'access_parts' => ['chapter1'],
                'part_from'    => ['chapter1' => 5],
                'part_to'      => ['chapter1' => 99],
            ]))
            ->assertRedirect("/admin/bluebooks/{$bluebook->id}/edit")
            ->assertSessionHasErrors('access_parts');

        $this->assertSame(Bluebook::ACCESS_PUBLIC, $bluebook->fresh()->access_level);
    }
}
