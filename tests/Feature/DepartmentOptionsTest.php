<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DepartmentOptionsTest extends TestCase
{
    use RefreshDatabase;

    private function uploader(): array
    {
        return ['id' => 1, 'name' => 'S T', 'email' => 't@my.cspc.edu.ph', 'role' => 'Student', 'canUpload' => true];
    }

    private function admin(): array
    {
        return ['id' => 2, 'name' => 'Adm', 'email' => 'adm@cspc.edu.ph', 'role' => 'Admin', 'canUpload' => true];
    }

    public function test_department_config_has_corrected_ccs_and_cspc_buhi(): void
    {
        $depts = config('departments');
        $this->assertSame('College of Computer Studies', $depts['CCS']['name']);
        $this->assertArrayHasKey('CSPC Buhi', $depts);
    }

    public function test_config_program_lists_match_what_was_specified(): void
    {
        $depts = config('departments');
        $this->assertSame([
            'Bachelor of Science in Office Administration',
            'Bachelor of Early Childhood Education',
        ], $depts['CSPC Buhi']['programs']);
        $this->assertSame([
            'Bachelor of Science in Nursing',
            'Bachelor of Science in Midwifery',
        ], $depts['CHS']['programs']);
        $this->assertSame([
            'Bachelor of Science in Human Services',
            'Bachelor of Arts in English Language Studies',
            'Bachelor of Science in Development Communication',
            'Bachelor of Public Administration',
            'Bachelor of Science in Mathematics',
            'Bachelor of Science in Applied Mathematics',
        ], $depts['CAS']['programs']);
    }

    public function test_upload_form_lists_new_departments_and_programs(): void
    {
        $res = $this->withSession(['user' => $this->uploader()])->get('/student/upload');
        $res->assertOk();
        $res->assertSee('CCS — College of Computer Studies', false);
        $res->assertSee('CSPC Buhi', false);
        $res->assertSee('Bachelor of Science in Office Administration', false);
        $res->assertSee('Bachelor of Early Childhood Education', false);
        $res->assertSee('Bachelor of Science in Midwifery', false);
        $res->assertSee('Bachelor of Arts in English Language Studies', false);
        $res->assertDontSee('Computing Studies', false);
        $res->assertDontSee('Bachelor of Arts in Communication', false); // old CAS program removed
    }

    public function test_admin_bluebook_form_lists_new_departments_and_programs(): void
    {
        User::create(['name' => 'Adm', 'email' => 'adm@cspc.edu.ph', 'password' => Hash::make('x'), 'role' => 'Admin']);
        $res = $this->withSession(['user' => $this->admin()])->get('/admin/bluebooks/new');
        $res->assertOk();
        $res->assertSee('CSPC Buhi', false);
        $res->assertSee('College of Computer Studies', false);
        $res->assertSee('Bachelor of Science in Office Administration', false);
        $res->assertSee('Bachelor of Science in Midwifery', false);
        $res->assertDontSee('Computing Studies', false);
    }

    public function test_browse_filter_lists_cspc_buhi(): void
    {
        $res = $this->withSession(['user' => $this->uploader()])->get('/student/bluebooks');
        $res->assertOk();
        $res->assertSee('CSPC Buhi', false);
    }

    public function test_uploading_a_cspc_buhi_bluebook_is_accepted(): void
    {
        Storage::fake('local');
        Queue::fake();

        $pdf = UploadedFile::fake()->createWithContent('b.pdf', "%PDF-1.4\n%%EOF");

        $res = $this->withSession(['user' => $this->uploader()])->post('/student/upload', [
            'title' => 'A CSPC Buhi Campus Study', 'authors' => 'Cruz, J',
            'department' => 'CSPC Buhi', 'program' => 'Bachelor of Science in Office Administration',
            'year' => 2025, 'adviser' => 'Prof X', 'pages' => 50,
            'keywords' => 'buhi, campus', 'abstract' => 'A study conducted at the CSPC Buhi campus.',
            'access_level' => 'public',
            'file' => $pdf,
        ]);

        $res->assertOk();
        $this->assertDatabaseHas('bluebooks', [
            'title' => 'A CSPC Buhi Campus Study',
            'department' => 'CSPC Buhi',
            'program' => 'Bachelor of Science in Office Administration',
        ]);
    }

    private function uploadPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'A Mismatched Paper', 'authors' => 'Cruz, J',
            'department' => 'CCS', 'program' => 'Bachelor of Science in Information Technology',
            'year' => 2025, 'adviser' => 'Prof X', 'pages' => 50,
            'keywords' => 'k', 'abstract' => 'A.',
            'file' => UploadedFile::fake()->createWithContent('b.pdf', "%PDF-1.4\n%%EOF"),
        ], $overrides);
    }

    public function test_upload_rejects_a_program_from_another_department(): void
    {
        Storage::fake('local');
        Queue::fake();

        $this->withSession(['user' => $this->uploader()])
            ->post('/student/upload', $this->uploadPayload(['program' => 'Bachelor of Science in Nursing']))
            ->assertOk()
            ->assertSee('Please choose a program offered by the selected department.');

        $this->assertDatabaseMissing('bluebooks', ['title' => 'A Mismatched Paper']);
    }

    public function test_upload_rejects_an_unknown_department(): void
    {
        Storage::fake('local');
        Queue::fake();

        $this->withSession(['user' => $this->uploader()])
            ->post('/student/upload', $this->uploadPayload(['department' => 'XYZ']))
            ->assertOk();

        $this->assertDatabaseMissing('bluebooks', ['title' => 'A Mismatched Paper']);
    }

    public function test_programs_are_tagged_with_their_department_for_the_picker(): void
    {
        $res = $this->withSession(['user' => $this->uploader()])->get('/student/upload');

        $res->assertSee('data-department-picker="upload-program"', false);
        $res->assertSee('data-department="CHS"', false);
    }

    public function test_ctde_majors_are_named_in_full(): void
    {
        $programs = config('departments')['CTDE']['programs'];

        $this->assertContains('Bachelor of Technical-Vocational Teacher Education, Major in Fish Processing', $programs);
        $this->assertNotContains('Fish Processing', $programs);
        $this->assertNotContains('Bachelor of Technical-Vocational Teacher Education, Major in:', $programs);
    }

    public function test_admin_edit_keeps_a_program_no_longer_listed(): void
    {
        User::create(['name' => 'Adm', 'email' => 'adm@cspc.edu.ph', 'password' => Hash::make('x'), 'role' => 'Admin']);
        $b = \App\Models\Bluebook::create([
            'title' => 'Old Program Paper', 'authors' => ['Cruz, J'], 'year' => 2019, 'department' => 'CAS',
            'program' => 'Bachelor of Arts in Communication', 'keywords' => ['k'], 'abstract' => 'A.', 'adviser' => 'Dr. A',
            'status' => 'Pending', 'pages' => 10, 'uploaded_by' => 'a@cspc.edu.ph', 'uploaded_by_name' => 'A', 'date_added' => '2019-01-01',
        ]);

        $this->withSession(['user' => $this->admin()])->get("/admin/bluebooks/{$b->id}/edit")
            ->assertOk()
            ->assertSee('Current (no longer listed)')
            ->assertSee('<option value="Bachelor of Arts in Communication" selected>', false);

        $this->withSession(['user' => $this->admin()])->post("/admin/bluebooks/{$b->id}/edit", [
            'title' => 'Old Program Paper, Retitled', 'authors' => 'Cruz, J', 'year' => 2019, 'department' => 'CAS',
            'program' => 'Bachelor of Arts in Communication', 'keywords' => 'k', 'abstract' => 'A.', 'adviser' => 'Dr. A',
            'pages' => 10, 'access_level' => 'public',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Old Program Paper, Retitled', $b->fresh()->title);
    }

    public function test_admin_add_rejects_a_program_from_another_department(): void
    {
        User::create(['name' => 'Adm', 'email' => 'adm@cspc.edu.ph', 'password' => Hash::make('x'), 'role' => 'Admin']);

        $this->withSession(['user' => $this->admin()])
            ->post('/admin/bluebooks/new', $this->uploadPayload(['program' => 'Bachelor of Science in Nursing']))
            ->assertSessionHasErrors('program');

        $this->assertDatabaseMissing('bluebooks', ['title' => 'A Mismatched Paper']);
    }
}
