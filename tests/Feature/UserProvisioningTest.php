<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\AccountSetup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class UserProvisioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_created_users_receive_setup_email_and_are_required_to_verify_and_change_password(): void
    {
        Notification::fake();
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.test',
            'password' => Hash::make('A-long-test-password'),
            'role' => 'admin',
            'status' => 'active',
        ]);

        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'New Instructor',
            'email' => 'instructor@example.test',
            'role' => 'instructor',
            'status' => 'active',
        ])->assertRedirect('/admin/users');

        $newUser = User::where('email', 'instructor@example.test')->firstOrFail();
        $this->assertTrue($newUser->requires_email_verification);
        $this->assertTrue($newUser->must_change_password);
        $this->assertNull($newUser->email_verified_at);
        Notification::assertSentTo($newUser, AccountSetup::class);
    }
}
