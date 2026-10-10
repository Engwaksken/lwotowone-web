<?php
namespace Tests\Feature;

use App\Models\User;
use App\Services\{EventParticipation, Workflow};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Schema};
use Tests\TestCase;

class EventSurveyMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_backfills_links_and_contacts_and_retry_preserves_registration_history(): void
    {
        $user = User::create(['name' => 'Existing learner', 'email' => 'existing@example.test', 'password' => 'A-long-test-password', 'role' => 'participant', 'status' => 'active']);
        $event = Workflow::insert('events', ['title' => 'Legacy event', 'description' => 'Text', 'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHours(2), 'location' => 'Kampala', 'capacity' => 20, 'status' => 'published']);
        $registration = Workflow::insert('event_registrations', ['user_id' => $user->id, 'event_id' => $event, 'status' => 'registered']);
        $created = DB::table('event_registrations')->find($registration)->created_at;
        $migration = require database_path('migrations/2026_10_10_000005_add_event_links_and_surveys.php');
        $migration->down(); $migration->up(); $token = DB::table('events')->where('id', $event)->value('registration_token'); $migration->up();
        $this->assertNotEmpty($token); $this->assertSame($token, DB::table('events')->where('id', $event)->value('registration_token'));
        $this->assertDatabaseHas('event_registrations', ['id' => $registration, 'created_at' => $created, 'status' => 'registered', 'participant_name' => $user->name, 'participant_email' => $user->email]);
        $this->assertTrue(Schema::hasTable('surveys')); $this->assertTrue(Schema::hasTable('survey_responses'));
    }

    public function test_rollback_refuses_to_delete_guest_registration_data(): void
    {
        $event = Workflow::insert('events', ['title' => 'Event', 'description' => 'Text', 'starts_at' => now()->addDay(), 'ends_at' => now()->addDays(2), 'location' => 'Kampala', 'capacity' => 20, 'status' => 'published']);
        EventParticipation::register($event, null, ['name' => 'Guest', 'email' => 'guest@example.test']);
        $migration = require database_path('migrations/2026_10_10_000005_add_event_links_and_surveys.php');
        try { $migration->down(); $this->fail('Guest registrations must be preserved.'); } catch (\RuntimeException $exception) { $this->assertStringContainsString('No data has been removed', $exception->getMessage()); }
        $this->assertDatabaseCount('event_registrations', 1); $this->assertTrue(Schema::hasTable('surveys'));
    }
}
