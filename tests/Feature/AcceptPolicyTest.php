<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The first-login consent gate.
 *
 * Acceptance is stamped once on users.policy_accepted_at and never asked
 * again, so it is the system's only record that the account holder was shown
 * the Terms and Conditions and Privacy Policy. It therefore has to require a
 * deliberate agreement rather than a bare form post.
 */
class AcceptPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function student(): array
    {
        User::create([
            'name'       => 'Test Student',
            'email'      => 'tester@my.cspc.edu.ph',
            'password'   => Hash::make('secret123'),
            'role'       => 'Student',
            'can_upload' => false,
        ]);

        return [
            'id'        => 1,
            'name'      => 'Test Student',
            'email'     => 'tester@my.cspc.edu.ph',
            'role'      => 'Student',
            'canUpload' => false,
        ];
    }

    public function test_the_gate_links_to_both_published_documents(): void
    {
        $response = $this->withSession(['user' => $this->student()])->get('/student/policy');

        $response->assertOk();
        $response->assertSee(route('terms'), false);
        $response->assertSee(route('privacy'), false);
        $response->assertSee('Terms and Conditions');
        $response->assertSee('Privacy Policy');
    }

    /**
     * The gate renders the documents themselves, from the same partials the
     * public pages use, rather than a summary that can drift away from them.
     */
    public function test_the_gate_shows_the_actual_document_text(): void
    {
        $response = $this->withSession(['user' => $this->student()])->get('/student/policy');

        // Section headings unique to each published document.
        $response->assertSee('Who may use the System');        // Terms, section 2
        $response->assertSee('Submitting your own work');      // Terms, section 4
        $response->assertSee('Information we collect');        // Privacy, section 1
        $response->assertSee('Screen-capture detection');      // Privacy, section 5
    }

    public function test_agreeing_records_acceptance_and_continues(): void
    {
        $user = $this->student();

        $response = $this->withSession(['user' => $user])
            ->post('/student/policy/accept', ['agree_terms' => '1', 'agree_privacy' => '1']);

        $response->assertRedirect(route('student.dashboard'));
        $this->assertNotNull(User::where('email', $user['email'])->first()->policy_accepted_at);
    }

    public function test_posting_without_agreeing_does_not_record_acceptance(): void
    {
        $user = $this->student();

        $response = $this->withSession(['user' => $user])
            ->post('/student/policy/accept', []);

        $response->assertSessionHasErrors(['agree_terms', 'agree_privacy']);
        $this->assertNull(User::where('email', $user['email'])->first()->policy_accepted_at);
    }

    public function test_acceptance_names_the_documents_in_the_access_log(): void
    {
        $user = $this->student();

        $this->withSession(['user' => $user])
            ->post('/student/policy/accept', ['agree_terms' => '1', 'agree_privacy' => '1']);

        $this->assertDatabaseHas('logs', [
            'email'    => $user['email'],
            'action'   => 'Accepted Policy',
            'document' => 'Acceptable Use, Terms and Conditions, Privacy Policy',
        ]);
    }

    public function test_the_public_documents_are_reachable_without_signing_in(): void
    {
        $this->get('/terms')->assertOk()->assertSee('Terms and Conditions');
        $this->get('/privacy')->assertOk()->assertSee('Privacy Policy');
    }
    /** Agreeing to one document is not agreement to the other. */
    public function test_agreeing_to_only_the_terms_is_rejected(): void
    {
        $user = $this->student();

        $response = $this->withSession(['user' => $user])
            ->post('/student/policy/accept', ['agree_terms' => '1']);

        $response->assertSessionHasErrors('agree_privacy');
        $this->assertNull(User::where('email', $user['email'])->first()->policy_accepted_at);
    }

    public function test_agreeing_to_only_the_privacy_policy_is_rejected(): void
    {
        $user = $this->student();

        $response = $this->withSession(['user' => $user])
            ->post('/student/policy/accept', ['agree_privacy' => '1']);

        $response->assertSessionHasErrors('agree_terms');
        $this->assertNull(User::where('email', $user['email'])->first()->policy_accepted_at);
    }
}