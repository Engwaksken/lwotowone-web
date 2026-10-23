<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use App\Models\User;
use App\Notifications\AccountSetup;
use Tests\TestCase;

class RegistrationPhoneTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'New Learner',
            'email' => 'new-learner-'.uniqid().'@example.test',
            'password' => 'A-long-test-password',
            'password_confirmation' => 'A-long-test-password',
            'consent' => 1,
            'terms_read' => 1,
            'privacy_read' => 1,
        ];
    }

    public function test_web_registration_requires_a_phone_number(): void
    {
        $this->post('/register', $this->payload())
            ->assertSessionHasErrors(['phone']);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_web_registration_with_phone_creates_a_participant(): void
    {
        Notification::fake();
        $this->post('/register', $this->payload(['phone' => '+256700000123', 'preferred_language' => 'lg']))
            ->assertRedirect('/login');

        $user = User::where('phone', '+256700000123')->firstOrFail();
        $this->assertSame('lg', $user->preferred_language);
        $this->assertTrue($user->requires_email_verification);
        Notification::assertSentTo($user, AccountSetup::class);
        $this->post('/login', ['email' => $user->email, 'password' => 'A-long-test-password'])
            ->assertSessionHasErrors('email');

        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]);
        $this->get($url)->assertRedirect('/login');
        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
        $this->assertFalse($user->requires_email_verification);
    }

    public function test_api_registration_still_accepts_no_phone_number(): void
    {
        Notification::fake();
        $this->postJson('/api/register', $this->payload())
            ->assertCreated();
    }

    public function test_register_form_marks_phone_as_required(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertSee('name="phone"', false)
            ->assertSee('name="preferred_language"', false)
            ->assertSee('Luganda')
            ->assertSee('required', false);
    }
}
