<?php
namespace Tests\Feature;

use App\Models\User;
use App\Services\{EventParticipation, Sharing, Workflow};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EventSharingTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'manager'): User
    {
        return User::create(['name' => 'Test '.$role, 'email' => uniqid().'@example.test', 'password' => 'A-long-test-password', 'role' => $role, 'status' => 'active']);
    }

    private function event(int $capacity = 2): object
    {
        $id = Workflow::insert('events', ['title' => 'Community workshop', 'description' => 'Practical skills', 'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHours(3), 'location' => 'Kampala', 'capacity' => $capacity, 'status' => 'published']);
        EventParticipation::token($id); return DB::table('events')->find($id);
    }

    public function test_cms_creates_stable_event_links_and_qr_and_events_table_shows_rosters(): void
    {
        $this->actingAs($this->user())->post('/admin/events', ['title' => 'New shared event', 'description' => 'Welcome', 'starts_at' => now()->addDay()->toDateTimeString(), 'ends_at' => now()->addDay()->addHours(2)->toDateTimeString(), 'location' => 'Kampala', 'capacity' => 30, 'status' => 'published'])->assertRedirect()->assertSessionHasNoErrors();
        $event = DB::table('events')->first(); $this->assertNotEmpty($event->registration_token);
        $this->get('/admin/events')->assertOk()->assertSee('Registration link and QR')->assertSee('/admin/events/'.$event->id.'/attendance', false)->assertSee(url('/events/'.$event->registration_token));
        $svg = $this->get('/admin/events/'.$event->id.'/qr.svg')->assertOk()->assertHeader('Content-Type', 'image/svg+xml')->getContent();
        $this->assertSame(Sharing::qr(url('/events/'.$event->registration_token), 'unused')->getContent(), $svg);
        $this->put('/admin/events/'.$event->id, ['title' => 'Updated event', 'description' => 'Welcome', 'starts_at' => $event->starts_at, 'ends_at' => $event->ends_at, 'location' => 'Kampala', 'capacity' => 30, 'status' => 'published'])->assertSessionHasNoErrors();
        $this->assertSame($event->registration_token, DB::table('events')->value('registration_token'));
    }

    public function test_guest_registration_is_duplicate_safe_capacity_checked_and_not_a_user_account(): void
    {
        $event = $this->event(1);
        $this->get('/events/'.$event->registration_token)->assertOk()->assertSee('No account is required');
        $url = '/events/'.$event->registration_token.'/register'; $data = ['name' => 'Guest participant', 'email' => 'GUEST@example.test', 'phone' => '+256700000001'];
        $this->post($url, $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('event_registrations', ['event_id' => $event->id, 'user_id' => null, 'participant_name' => 'Guest participant', 'participant_email' => 'guest@example.test', 'status' => 'registered']);
        $this->assertDatabaseCount('users', 0);
        $this->post($url, $data)->assertSessionHasErrors('event_id');
        $this->post($url, ['name' => 'Another guest', 'email' => 'another@example.test'])->assertSessionHasErrors('event_id');
        $this->assertDatabaseCount('event_registrations', 1);
        $this->get('/events/'.$event->registration_token)->assertSee('This event is full')->assertDontSee('guest@example.test');
    }

    public function test_public_event_registration_does_not_require_paid_learning_and_uses_own_identity(): void
    {
        $event = $this->event(); $learner = $this->user('participant');
        $this->actingAs($learner)->post('/events/'.$event->registration_token.'/register', ['name' => 'Spoof', 'email' => 'spoof@example.test', 'status' => 'attended'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('event_registrations', ['event_id' => $event->id, 'user_id' => $learner->id, 'participant_name' => $learner->name, 'participant_email' => $learner->email, 'status' => 'registered']);
        $this->get('/events/'.$event->registration_token)->assertOk()->assertSee('Your registration status');
        $this->actingAs($this->user('instructor'))->post('/events/'.$event->registration_token.'/register')->assertForbidden();
    }

    public function test_attendance_is_scoped_audited_and_cannot_be_recorded_before_start_or_self_marked(): void
    {
        $event = $this->event(); $other = $this->event(); $learner = $this->user('participant');
        $id = EventParticipation::register($event->id, $learner); $otherId = EventParticipation::register($other->id, null, ['name' => 'Other guest', 'email' => 'other@example.test']);
        $url = '/admin/events/'.$event->id.'/attendance/'.$id;
        $this->actingAs($learner)->post($url, ['status' => 'attended'])->assertForbidden();
        $manager = $this->user(); $this->actingAs($manager)->post($url, ['status' => 'attended'])->assertSessionHasErrors('status');
        $this->post('/admin/reviews/event_registrations/'.$id, ['status' => 'attended'])->assertSessionHasErrors('status');
        $this->travel(1)->days(); $this->post($url, ['status' => 'attended'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('event_registrations', ['id' => $id, 'status' => 'attended', 'attendance_recorded_by' => $manager->id]);
        $this->assertNotNull(DB::table('event_registrations')->find($id)->attended_at);
        $this->post($url, ['status' => 'attended'])->assertSessionHasNoErrors(); $this->assertDatabaseCount('audit_logs', 1);
        $this->post('/admin/events/'.$event->id.'/attendance/'.$otherId, ['status' => 'attended'])->assertNotFound();
        $this->get('/admin/events/'.$event->id.'/attendance')->assertOk()->assertSee($learner->email)->assertDontSee('other@example.test');
        $csv = $this->get('/admin/events/'.$event->id.'/attendance.csv')->assertOk()->streamedContent(); $this->assertStringContainsString($learner->email, $csv); $this->assertStringContainsString('attended', $csv);
    }

    public function test_draft_started_and_cancelled_events_reject_public_registration_and_rosters_are_private(): void
    {
        $event = $this->event(); $url = '/events/'.$event->registration_token;
        DB::table('events')->where('id', $event->id)->update(['status' => 'draft']); $this->get($url)->assertNotFound();
        $this->post($url.'/register', ['name' => 'Guest', 'email' => 'guest@example.test'])->assertNotFound();
        DB::table('events')->where('id', $event->id)->update(['status' => 'published']); $this->travel(1)->days();
        $this->post($url.'/register', ['name' => 'Guest', 'email' => 'guest@example.test'])->assertSessionHasErrors('event_id');
        $this->actingAs($this->user('instructor'));
        foreach (['attendance', 'attendance.csv', 'qr.svg'] as $suffix) $this->get('/admin/events/'.$event->id.'/'.$suffix)->assertForbidden();
        $this->assertDatabaseCount('event_registrations', 0);
    }

    public function test_guest_cancellation_releases_a_place_and_reregistration_preserves_history(): void
    {
        $event = $this->event(1); $id = EventParticipation::register($event->id, null, ['name' => 'Guest', 'email' => 'guest@example.test']);
        $created = DB::table('event_registrations')->find($id)->created_at; $manager = $this->user();
        EventParticipation::mark($event->id, $id, 'cancelled', $manager);
        $this->assertSame($id, EventParticipation::register($event->id, null, ['name' => 'Guest', 'email' => 'guest@example.test']));
        $this->assertDatabaseHas('event_registrations', ['id' => $id, 'status' => 'registered', 'created_at' => $created, 'attendance_recorded_by' => null]);
    }
}
