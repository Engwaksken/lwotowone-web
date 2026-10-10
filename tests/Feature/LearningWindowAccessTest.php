<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LearningWindowAccessTest extends TestCase
{
    use RefreshDatabase;

    private function participant(bool $profileComplete = false): User
    {
        return User::create([
            'name' => 'New learner', 'email' => uniqid().'@example.test',
            'password' => 'A-long-test-password', 'role' => 'participant', 'status' => 'active',
            'profile_complete' => $profileComplete,
        ]);
    }

    private function completedLearner(): User
    {
        $learner = $this->participant();
        $learner->update(['profile_complete' => true]);

        return $learner->fresh();
    }

    private function staff(string $role): User
    {
        return User::create([
            'name' => ucfirst($role), 'email' => uniqid().'@example.test',
            'password' => 'A-long-test-password', 'role' => $role, 'status' => 'active',
        ]);
    }

    public function test_window_is_granted_when_profile_first_reaches_complete(): void
    {
        $learner = $this->completedLearner();

        $this->assertEqualsWithDelta(
            now()->addHours(24)->timestamp,
            $learner->full_access_until->timestamp,
            60
        );
        $this->assertFalse($learner->learning_access_paid);
    }

    public function test_window_is_not_reset_by_later_saves(): void
    {
        $learner = $this->completedLearner();
        $original = $learner->full_access_until->timestamp;

        $this->travel(1)->hours();
        $learner->update(['name' => 'Renamed learner', 'profile_complete' => true]);

        $this->assertSame($original, $learner->fresh()->full_access_until->timestamp);
    }

    public function test_learner_can_access_portal_pages_within_window(): void
    {
        $learner = $this->completedLearner();

        $this->actingAs($learner)->get('/portal/practice')->assertOk();
        $this->actingAs($learner)->get('/portal/learn')->assertOk();
    }

    public function test_learner_is_blocked_after_window_expires_without_payment(): void
    {
        $learner = $this->completedLearner();
        $this->travel(25)->hours();

        $this->actingAs($learner)->get('/portal/practice')
            ->assertRedirect('/portal/payment')
            ->assertSessionHas('learning_access_notice');
    }

    public function test_expired_learner_gets_json_403_with_payment_redirect_on_actions(): void
    {
        $learner = $this->completedLearner();
        $this->travel(25)->hours();

        $this->actingAs($learner)->postJson('/actions/practice', [])
            ->assertForbidden()
            ->assertJson(['redirect' => '/portal/payment']);
    }

    public function test_payment_dashboard_and_profile_remain_reachable_when_locked(): void
    {
        $learner = $this->completedLearner();
        $this->travel(25)->hours();

        $this->actingAs($learner)->get('/portal/payment')->assertOk();
        $this->actingAs($learner)->get('/dashboard')->assertOk();
        $this->actingAs($learner)->get('/profile')->assertOk();
    }

    public function test_learner_regains_access_after_payment(): void
    {
        $learner = $this->completedLearner();
        $this->travel(25)->hours();
        $learner->update(['learning_access_paid' => true]);

        $this->actingAs($learner->fresh())->get('/portal/practice')->assertOk();
    }

    public function test_manager_is_unaffected_by_learning_window(): void
    {
        $manager = $this->staff('manager');
        $this->assertNull($manager->full_access_until);

        $this->actingAs($manager)->get('/portal/practice')->assertForbidden();
    }

    public function test_admin_dashboard_is_unaffected_by_learning_window(): void
    {
        $admin = $this->staff('admin');
        $this->assertNull($admin->full_access_until);

        $this->actingAs($admin)->get('/dashboard')->assertOk();
    }

    public function test_incomplete_profile_is_sent_to_profile(): void
    {
        $learner = $this->participant(profileComplete: false);

        $this->actingAs($learner)->get('/portal/practice')->assertRedirect('/profile');
    }

    public function test_legacy_completed_unpaid_learner_without_window_is_locked(): void
    {
        $learner = $this->participant();
        DB::table('users')->where('id', $learner->id)->update(['profile_complete' => true]);

        $this->actingAs($learner->fresh())->get('/portal/practice')->assertRedirect('/portal/payment');
    }

    public function test_guest_is_redirected_to_login_from_gated_portal_page(): void
    {
        $this->get('/portal/practice')->assertRedirect('/login');
    }
}
