<?php

namespace Tests\Feature;

use App\Models\Bluebook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The access permission waiver is recorded by the admin on the bluebook edit
 * form, not chosen by the student on the upload form.
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

    public function test_upload_form_no_longer_asks_for_the_waiver(): void
    {
        $this->withSession(['user' => $this->uploader()])
            ->get('/student/upload')
            ->assertOk()
            ->assertDontSee('Access Permission Waiver');
    }

    public function test_admin_edit_form_shows_the_waiver_with_its_saved_choice(): void
    {
        $bluebook = $this->makeBluebook();
        $bluebook->update(['access_level' => Bluebook::ACCESS_CONSULTATION]);

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
        $bluebook->update(['access_level' => Bluebook::ACCESS_PARTIAL, 'access_parts' => ['abstract' => ['from' => 3, 'to' => 4]]]);

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
