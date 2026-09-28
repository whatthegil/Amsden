<?php

namespace Tests\Feature;

use App\Jobs\ProcessBluebookOcr;
use App\Models\Bluebook;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** A bluebook added from the admin side has its text read, as a student upload does. */
class AdminAddBluebookOcrTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $role = 'Admin'): array
    {
        $u = User::create(['name' => 'Staff', 'email' => strtolower(str_replace('-', '', $role)) . '@cspc.edu.ph', 'password' => Hash::make('x'), 'role' => $role]);

        return ['user' => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'role' => $role]];
    }

    private function form(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Library Copy', 'authors' => 'Cruz, Ana', 'year' => 2024, 'department' => 'CCS',
            'program' => 'Bachelor of Science in Information Technology', 'keywords' => 'library',
            'abstract' => 'A paper the library holds.', 'adviser' => 'Reyes, Jose P.', 'pages' => 40,
        ], $overrides);
    }

    public function test_adding_a_bluebook_with_a_pdf_queues_ocr(): void
    {
        Storage::fake('local');
        Queue::fake();

        $this->withSession($this->admin())->post('/admin/bluebooks/new', $this->form([
            'file' => UploadedFile::fake()->createWithContent('library.pdf', "%PDF-1.4\n%%EOF"),
        ]));

        $book = Bluebook::where('title', 'Library Copy')->firstOrFail();
        Queue::assertPushed(ProcessBluebookOcr::class, 1);
        Queue::assertPushed(fn (ProcessBluebookOcr $job) => (fn () => $this->bluebookId)->call($job) === $book->id);
    }

    public function test_a_sub_admins_addition_is_read_too(): void
    {
        Storage::fake('local');
        Queue::fake();

        $this->withSession($this->admin('Sub-Admin'))->post('/admin/bluebooks/new', $this->form([
            'file' => UploadedFile::fake()->createWithContent('library.pdf', "%PDF-1.4\n%%EOF"),
        ]));

        Queue::assertPushed(ProcessBluebookOcr::class, 1);
    }

    public function test_a_record_with_no_pdf_queues_nothing(): void
    {
        Queue::fake();

        $this->withSession($this->admin())->post('/admin/bluebooks/new', $this->form());

        $this->assertDatabaseHas('bluebooks', ['title' => 'Library Copy']);
        Queue::assertNothingPushed();
    }
}
