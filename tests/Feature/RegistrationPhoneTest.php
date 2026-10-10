<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->post('/register', $this->payload(['phone' => '+256700000123']))
            ->assertRedirect('/dashboard');

        $this->assertDatabaseHas('users', ['phone' => '+256700000123', 'role' => 'participant']);
    }

    public function test_api_registration_still_accepts_no_phone_number(): void
    {
        $this->postJson('/api/register', $this->payload())
            ->assertCreated();
    }

    public function test_register_form_marks_phone_as_required(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertSee('name="phone"', false)
            ->assertSee('required', false);
    }
}
