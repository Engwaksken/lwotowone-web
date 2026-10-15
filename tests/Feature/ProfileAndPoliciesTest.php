<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\{ProfileProgress, Workflow};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Notification};
use Tests\TestCase;

class ProfileAndPoliciesTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return $overrides + ['name' => 'New Learner', 'email' => 'learner-'.uniqid().'@example.test', 'phone' => '+256700000001', 'password' => 'A-long-test-password', 'password_confirmation' => 'A-long-test-password', 'consent' => 1, 'terms_read' => 1, 'privacy_read' => 1];
    }

    private function participant(array $extra = []): User
    {
        return User::create(['name' => 'Learner', 'email' => uniqid().'@example.test', 'password' => 'A-long-test-password', 'role' => 'participant', 'status' => 'active', 'phone' => '+256700000002'] + $extra);
    }

    public function test_register_page_shows_both_policies_in_reading_popups(): void
    {
        DB::table('pages')->insert(['title' => 'Terms of use', 'slug' => 'terms', 'body' => 'Custom terms body text', 'status' => 'published', 'created_at' => now(), 'updated_at' => now()]);
        $this->get('/register')->assertOk()
            ->assertSee('data-policy-dialog="terms"', false)->assertSee('data-policy-dialog="privacy"', false)
            ->assertSee('Custom terms body text')->assertSee('We collect the details you give us')
            ->assertSee('name="terms_read"', false)->assertSee('name="privacy_read"', false);
    }

    public function test_registration_requires_reading_both_policies_and_records_acceptance(): void
    {
        Notification::fake();
        $this->post('/register', $this->payload(['terms_read' => '']))->assertSessionHasErrors('terms_read');
        $this->post('/register', $this->payload(['privacy_read' => '']))->assertSessionHasErrors('privacy_read');
        $this->assertDatabaseCount('users', 0);

        $this->post('/register', $this->payload(['email' => 'reader@example.test']))->assertRedirect('/login')->assertSessionHasNoErrors();
        $this->assertNotNull(User::where('email', 'reader@example.test')->value('terms_accepted_at'));
    }

    public function test_profile_progress_reflects_missing_answers(): void
    {
        $learner = $this->participant();
        $this->assertSame(20, ProfileProgress::percent($learner));
        $this->assertContains('Course choice', ProfileProgress::missing($learner));

        $this->actingAs($learner)->get('/profile')->assertOk()->assertSee('Complete your profile to unlock learning')->assertSee('aria-valuenow="20"', false);
        $this->get('/dashboard')->assertRedirect('/profile');

        $learner->update(['profile_complete' => true]);
        $this->assertSame(100, ProfileProgress::percent($learner->fresh()));
        $this->get('/profile')->assertOk()->assertSee('Profile complete');
        $this->get('/dashboard')->assertOk();
    }

    public function test_incomplete_profiles_cannot_reach_learning_or_events(): void
    {
        $learner = $this->participant();
        foreach (['/portal/learn', '/portal/events', '/portal/opportunities', '/portal/mentorship'] as $path) {
            $this->actingAs($learner)->get($path)->assertRedirect('/profile');
        }
        $id = Workflow::insert('events', ['title' => 'Open day', 'description' => 'Welcome', 'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHour(), 'location' => 'Gulu', 'capacity' => 10, 'status' => 'published', 'registration_token' => 'open-day-token']);
        $this->get('/events/open-day-token')->assertOk()->assertSee('Complete your learner profile to register');
        $this->post('/events/open-day-token/register')->assertRedirect('/profile');
        $this->assertDatabaseMissing('event_registrations', ['event_id' => $id]);
    }
    public function test_mobile_api_exposes_policies_profile_progress_and_free_access(): void
    {
        $this->getJson('/api/policies')->assertOk()->assertJsonPath('policies.terms.title', 'Terms of use')->assertJsonPath('policies.privacy.title', 'Privacy notice');

        DB::table('mel_settlements')->insert(['name' => 'Bidibidi', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        Workflow::insert('events', ['title' => 'Paid fair', 'description' => 'Welcome', 'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHour(), 'location' => 'Arua', 'capacity' => 10, 'status' => 'published', 'payment_amount' => 5000, 'registration_token' => 'paid-fair']);
        $learner = $this->participant();
        $snapshot = $this->actingAs($learner, 'sanctum')->getJson('/api/snapshot')->assertOk();
        $snapshot->assertJsonPath('access.profile_complete', false)->assertJsonPath('access.profile_percent', 20)->assertJsonPath('access.free_access', true)->assertJsonPath('access.learning_open', false)
            ->assertJsonPath('settlements.0.name', 'Bidibidi')->assertJsonPath('user.profile_complete', false)->assertJsonPath('events.0.fee_due', false);

        $this->endFreeAccess();
        $learner->update(['profile_complete' => true]);
        $this->getJson('/api/snapshot')->assertJsonPath('access.free_access', false)->assertJsonPath('access.profile_percent', 100)->assertJsonPath('events.0.fee_due', true)->assertJsonPath('events.0.currency', 'UGX');
    }
}
