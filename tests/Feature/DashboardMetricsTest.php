<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\DashboardMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DashboardMetricsTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, array $extra = []): User
    {
        return User::create($extra + [
            'name' => ucfirst($role).' User',
            'email' => $role.'-'.uniqid().'@example.test',
            'password' => Hash::make('A-long-test-password'),
            'role' => $role,
            'status' => 'active',
        ]);
    }

    public function test_manager_gets_the_full_metrics_contract(): void
    {
        $admin = $this->user('admin');
        $metrics = app(DashboardMetrics::class)->forUser($admin);

        $this->assertSame(
            ['attention', 'money', 'growth', 'programmes', 'events', 'activity'],
            array_keys($metrics)
        );
        $this->assertIsArray($metrics['money']);
        $this->assertNull($metrics['money']['payments_this_month_amount']);
        $this->assertSame(
            ['learners_awaiting_payment', 'applications_to_review', 'submissions_to_review', 'practice_logs_pending', 'bookings_requested', 'contacts_new'],
            array_keys($metrics['attention'])
        );
    }

    public function test_money_is_hidden_from_non_manager_staff(): void
    {
        $mentor = $this->user('mentor');

        $this->assertNull(app(DashboardMetrics::class)->forUser($mentor)['money']);
        $this->actingAs($mentor)->get('/dashboard')->assertOk()->assertDontSee('Money');
    }

    public function test_awaiting_payment_excludes_paid_and_active_window_learners(): void
    {
        // Profile complete, unpaid, window expired: counted.
        $expired = $this->user('participant', ['profile_complete' => false]);
        DB::table('users')->where('id', $expired->id)->update(['profile_complete' => true, 'full_access_until' => now()->subDay()]);
        // Profile complete with an active window (granted automatically on completion): not counted.
        $this->user('participant', ['profile_complete' => true]);
        // Paid: not counted.
        $this->user('participant', ['profile_complete' => true, 'learning_access_paid' => true]);

        $metrics = app(DashboardMetrics::class)->forUser($this->user('admin'));

        $this->assertSame(1, $metrics['attention']['learners_awaiting_payment']);
    }

    public function test_profile_completion_rate_and_funnel(): void
    {
        $this->user('participant', ['profile_complete' => true, 'learning_access_paid' => true]);
        $this->user('participant', ['profile_complete' => false]);
        $this->user('participant', ['profile_complete' => false]);
        $this->user('participant', ['profile_complete' => false]);

        $growth = app(DashboardMetrics::class)->forUser($this->user('admin'))['growth'];

        $this->assertSame(4, $growth['participants_total']);
        $this->assertSame(25.0, $growth['profile_complete_rate']);
        $this->assertSame(['registered' => 4, 'profile_complete' => 1, 'paid' => 1], $growth['funnel']);
    }

    public function test_upcoming_events_show_registrations_and_capacity(): void
    {
        $owner = $this->user('admin');
        $learner = $this->user('participant', ['profile_complete' => true]);
        $eventId = DB::table('events')->insertGetId([
            'title' => 'Skills day',
            'description' => 'Test',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
            'location' => 'Kampala',
            'capacity' => 10,
            'status' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('event_registrations')->insert([
            'user_id' => $learner->id,
            'event_id' => $eventId,
            'status' => 'registered',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $events = app(DashboardMetrics::class)->forUser($owner)['events'];

        $this->assertCount(1, $events['upcoming']);
        $this->assertSame('Skills day', $events['upcoming'][0]['title']);
        $this->assertSame(1, $events['upcoming'][0]['registered']);
        $this->assertSame(10, $events['upcoming'][0]['capacity']);
    }

    public function test_staff_dashboard_renders_the_new_sections(): void
    {
        $admin = $this->user('admin');

        $this->actingAs($admin)->get('/dashboard')
            ->assertOk()
            ->assertSee('Today at a glance')
            ->assertSee('Awaiting payment')
            ->assertSee('Key numbers')
            ->assertSee('Learner funnel')
            ->assertSee('Upcoming events')
            ->assertSee('Recent activity');
    }
}
