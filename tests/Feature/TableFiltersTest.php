<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TableFiltersTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'participant'): User
    {
        return User::create([
            'name' => ucfirst($role),
            'email' => uniqid().'@example.test',
            'password' => 'A-long-test-password',
            'role' => $role,
            'status' => 'active',
            'profile_complete' => $role === 'participant',
            'learning_access_paid' => $role === 'participant',
        ]);
    }

    private function skill(): int
    {
        return DB::table('skills')->insertGetId([
            'title' => 'Food preparation',
            'category' => 'Hospitality',
            'description' => 'Safe food preparation and service',
            'status' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_practical_activity_table_supports_literal_search_periods_and_pagination(): void
    {
        $this->travelTo(Carbon::parse('2026-10-07 12:00:00'));
        $user = $this->user();
        $skill = $this->skill();
        foreach (range(1, 12) as $day) {
            DB::table('practice_logs')->insert([
                'user_id' => $user->id,
                'skill_id' => $skill,
                'title' => $day === 7 ? 'Special 100%_! activity' : "Practice task {$day}",
                'body' => "Learning notes {$day}",
                'minutes' => 20,
                'practised_on' => Carbon::parse('2026-09-29')->addDays($day)->toDateString(),
                'status' => $day % 2 ? 'pending' : 'verified',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->actingAs($user)->get('/portal/practice')->assertOk()->assertViewHas('practiceLogs', fn ($logs) => $logs->total() === 12 && $logs->count() === 10);
        $this->get('/portal/practice?'.http_build_query(['q' => '%_!', 'period' => 'all']))
            ->assertOk()->assertViewHas('practiceLogs', fn ($logs) => $logs->total() === 1);
        $this->get('/portal/practice?'.http_build_query([
            'period' => 'custom', 'start_date' => '2026-10-05', 'end_date' => '2026-10-07',
        ]))->assertOk()->assertViewHas('practiceLogs', fn ($logs) => $logs->total() === 3);
    }

    public function test_review_queue_supports_search_period_status_and_pagination(): void
    {
        $this->travelTo(Carbon::parse('2026-10-07 12:00:00'));
        $manager = $this->user('manager');
        foreach (range(1, 23) as $day) {
            DB::table('contacts')->insert([
                'name' => "Contact {$day}",
                'email' => "contact{$day}@example.test",
                'message' => $day === 7 ? 'Special harvest enquiry' : "General enquiry {$day}",
                'status' => $day % 2 ? 'new' : 'resolved',
                'created_at' => Carbon::parse('2026-09-15')->addDays($day)->toDateTimeString(),
                'updated_at' => now(),
            ]);
        }

        $this->actingAs($manager)->get('/admin/reviews/contacts')->assertOk()
            ->assertViewHas('rows', fn ($rows) => $rows->total() === 23 && $rows->count() === 20);
        $this->get('/admin/reviews/contacts?'.http_build_query(['q' => 'harvest', 'status' => 'new']))
            ->assertOk()->assertViewHas('rows', fn ($rows) => $rows->total() === 1);
        $this->get('/admin/reviews/contacts?period=week')
            ->assertOk()->assertViewHas('rows', fn ($rows) => $rows->total() === 4);
    }

    public function test_cms_record_table_filters_by_search_and_creation_period(): void
    {
        $this->travelTo(Carbon::parse('2026-10-07 12:00:00'));
        $admin = $this->user('admin');
        foreach (range(1, 22) as $day) {
            $createdAt = $day <= 3
                ? Carbon::parse('2026-10-04')->addDays($day)
                : Carbon::parse('2026-09-01')->addDays($day);
            DB::table('pages')->insert([
                'title' => $day === 8 ? 'Featured guidance page' : "Information page {$day}",
                'slug' => "information-{$day}",
                'body' => 'Page content',
                'status' => 'published',
                'meta_description' => null,
                'created_at' => $createdAt->toDateTimeString(),
                'updated_at' => now(),
            ]);
        }

        $this->actingAs($admin)->get('/admin/pages')->assertOk()
            ->assertViewHas('rows', fn ($rows) => $rows->total() === 22 && $rows->count() === 20);
        $this->get('/admin/pages?q=Featured')->assertOk()->assertViewHas('rows', fn ($rows) => $rows->total() === 1);
        $this->get('/admin/pages?period=week')->assertOk()->assertViewHas('rows', fn ($rows) => $rows->total() === 3);
    }
}
