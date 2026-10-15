<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LayoutNavigationTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::create(['name' => ucfirst($role), 'email' => uniqid().'@example.test', 'password' => 'A-long-test-password', 'role' => $role, 'status' => 'active', 'profile_complete' => true]);
    }

    public function test_signed_in_users_see_public_pages_without_the_dashboard_sidebar(): void
    {
        foreach (['participant', 'admin'] as $role) {
            $this->actingAs($this->user($role));
            foreach (['/', '/explore/programs', '/contact', '/calls'] as $path) {
                $this->get($path)->assertOk()
                    ->assertDontSee('id="account-navigation"', false)
                    ->assertSee('href="/explore/programs"', false)
                    ->assertSee('class="site-footer"', false)
                    ->assertSee('href="/dashboard"', false)
                    ->assertDontSee('class="button small" href="/register"', false);
            }
        }
    }

    public function test_workspace_pages_keep_the_sidebar_without_public_footer(): void
    {
        $this->actingAs($this->user('admin'));
        foreach (['/dashboard', '/admin/events', '/profile'] as $path) {
            $this->get($path)->assertOk()->assertSee('id="account-navigation"', false)->assertDontSee('class="site-footer"', false);
        }
        $this->actingAs($this->user('participant'))->get('/portal/learn')->assertOk()->assertSee('id="account-navigation"', false);
    }
}
