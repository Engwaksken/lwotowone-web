<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Renders every staff and participant page once, so template errors in
 * shared components (tables, filter rows, view dialogs) fail the suite.
 */
class PageSmokeTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, array $extra = []): User
    {
        return User::create($extra + [
            'name' => ucfirst($role).' Tester',
            'email' => $role.'-smoke-'.uniqid().'@example.test',
            'password' => Hash::make('A-long-test-password'),
            'role' => $role,
            'status' => 'active',
        ]);
    }

    private function assertPagesRender(User $user, array $urls): void
    {
        foreach ($urls as $url) {
            $status = $this->actingAs($user)->get($url)->getStatusCode();
            $this->assertSame(200, $status, "Expected 200 for {$url}, got {$status}");
        }
    }

    public function test_every_admin_page_renders_for_admin(): void
    {
        $admin = $this->user('admin');

        $urls = [
            '/dashboard',
            '/admin/calls',
            '/admin/certificates',
            '/admin/enrollment',
            '/admin/mel',
            '/admin/site-settings',
            '/admin/reports/impact.csv',
        ];
        foreach (array_keys(config('modules')) as $module) {
            $urls[] = '/admin/'.$module;
        }
        foreach (['submissions', 'practice_logs', 'bookings', 'applications', 'event_registrations', 'contacts', 'audit_logs'] as $type) {
            $urls[] = '/admin/reviews/'.$type;
        }

        $this->assertPagesRender($admin, $urls);
    }

    public function test_every_admin_page_renders_for_manager_without_admin_only_settings(): void
    {
        $manager = $this->user('manager');

        $this->assertPagesRender($manager, [
            '/dashboard',
            '/admin/calls',
            '/admin/enrollment',
            '/admin/mel',
            '/admin/site-settings',
        ]);
    }

    public function test_every_portal_page_renders_for_paid_participant(): void
    {
        $participant = $this->user('participant', [
            'profile_complete' => true,
            'learning_access_paid' => true,
        ]);

        $this->assertPagesRender($participant, [
            '/dashboard',
            '/profile',
            '/portal/learn',
            '/portal/practice',
            '/portal/mentorship',
            '/portal/enterprise',
            '/portal/events',
            '/portal/opportunities',
            '/portal/notifications',
            '/portal/payment',
        ]);
    }

    public function test_mel_dashboard_shows_gaps_add_actions_and_evidence(): void
    {
        $admin = $this->user('admin');
        $this->user('participant', ['profile_complete' => false]);
        $this->user('participant', ['profile_complete' => true, 'learning_access_paid' => true]);

        $this->actingAs($admin)->get('/admin/mel')
            ->assertOk()
            ->assertSee('Fix data gaps')
            ->assertSee('Profile not completed')
            ->assertSee('Enrolled without a learner number')
            ->assertSee('Add a record')
            ->assertSee('data-dialog-open="mel-add-teachers"', false)
            ->assertSee('Evidence documents')
            ->assertSee('Upload evidence');
    }

    public function test_certificate_design_page_renders_for_a_course(): void
    {
        $admin = $this->user('admin');
        $now = now();
        $programId = \Illuminate\Support\Facades\DB::table('programs')->insertGetId([
            'title' => 'Smoke programme', 'slug' => 'smoke-programme', 'summary' => 'Test', 'body' => 'Test', 'status' => 'published', 'created_at' => $now, 'updated_at' => $now,
        ]);
        $courseId = \Illuminate\Support\Facades\DB::table('courses')->insertGetId([
            'title' => 'Smoke course', 'program_id' => $programId, 'instructor_id' => $admin->id, 'summary' => 'Test',
            'level' => 'Beginner', 'duration_hours' => 4, 'status' => 'published', 'created_at' => $now, 'updated_at' => $now,
        ]);

        $this->assertPagesRender($admin, ["/admin/certificates/{$courseId}/template"]);
    }

    public function test_participant_in_free_window_can_open_portal_pages(): void
    {
        $participant = $this->user('participant', ['profile_complete' => true]);

        $this->assertPagesRender($participant, [
            '/dashboard',
            '/portal/learn',
            '/portal/enterprise',
            '/portal/events',
        ]);
        $this->actingAs($participant)->get('/portal/learn')->assertSee('You have full access until');
    }
}
