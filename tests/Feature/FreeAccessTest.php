<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\{FreeAccess, LearningAccess, Workflow};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FreeAccessTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'participant', array $extra = []): User
    {
        return User::create(['name' => ucfirst($role), 'email' => uniqid().'@example.test', 'password' => 'A-long-test-password', 'role' => $role, 'status' => 'active'] + $extra);
    }

    private function expiredLearner(): User
    {
        $learner = $this->user('participant', ['profile_complete' => true]);
        DB::table('users')->where('id', $learner->id)->update(['full_access_until' => now()->subHour()]);
        return $learner->fresh();
    }

    private function course(): int
    {
        $teacher = $this->user('instructor');
        $program = Workflow::insert('programs', ['title' => 'Program', 'slug' => uniqid(), 'summary' => 'Text', 'body' => 'Text', 'category' => 'TVET', 'status' => 'published']);
        return Workflow::insert('courses', ['title' => 'Course', 'program_id' => $program, 'instructor_id' => $teacher->id, 'summary' => 'Text', 'duration_hours' => 10, 'status' => 'published']);
    }

    public function test_learners_use_everything_free_until_an_expiry_is_set(): void
    {
        $learner = $this->expiredLearner();
        $this->assertTrue(FreeAccess::active());
        $this->assertTrue($learner->learningAccessOpen());
        $this->actingAs($learner)->get('/portal/learn')->assertOk()->assertSee('Free access:')->assertSee('No payment is needed');
        $this->get('/portal/payment')->assertOk()->assertSee('No payment needed yet');

        $this->endFreeAccess();
        $this->assertFalse($learner->fresh()->learningAccessOpen());
        $this->get('/portal/learn')->assertRedirect('/portal/payment');
    }

    public function test_expired_course_trials_stay_open_during_free_period(): void
    {
        $learner = $this->expiredLearner();
        $course = $this->course();
        DB::table('enrolments')->insert(['user_id' => $learner->id, 'course_id' => $course, 'trial_started_at' => now()->subDay(), 'trial_expires_at' => now()->subHours(12), 'created_at' => now(), 'updated_at' => now()]);

        $this->assertTrue(LearningAccess::allowed($learner, $course));
        $this->endFreeAccess();
        $this->assertFalse(LearningAccess::allowed($learner, $course));
    }

    public function test_free_period_with_future_end_date_shows_the_date(): void
    {
        DB::table('settings')->insert(['key' => FreeAccess::SETTING, 'value' => now()->addWeek()->toDateTimeString(), 'created_at' => now(), 'updated_at' => now()]);
        $this->assertTrue(FreeAccess::active());
        $this->actingAs($this->expiredLearner())->get('/portal/learn')->assertOk()->assertSee(now()->addWeek()->format('d M Y'))->assertSee('subscribe or pay');
    }

    public function test_managers_set_and_end_the_free_period(): void
    {
        $this->actingAs($this->user())->put('/admin/site-settings/free-access', ['free_access_ends_at' => now()->addDay()->format('Y-m-d\TH:i')])->assertForbidden();

        $manager = $this->user('manager');
        $this->actingAs($manager)->get('/admin/site-settings')->assertOk()->assertSee('Free access period')->assertSee('Free access on');
        $this->put('/admin/site-settings/free-access', ['free_access_ends_at' => now()->addDays(3)->format('Y-m-d\TH:i')])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue(FreeAccess::active());
        $this->assertEqualsWithDelta(now()->addDays(3)->timestamp, FreeAccess::endsAt()->timestamp, 60);

        $this->put('/admin/site-settings/free-access', ['end_now' => 1])->assertRedirect();
        $this->assertFalse(FreeAccess::active());
        $this->get('/admin/site-settings')->assertSee('Free access ended');

        $this->put('/admin/site-settings/free-access', ['free_access_ends_at' => ''])->assertRedirect();
        $this->assertTrue(FreeAccess::active());
        $this->assertNull(FreeAccess::endsAt());
    }

    public function test_event_payment_amount_is_optional_and_shown_once_free_access_ends(): void
    {
        $admin = $this->user('admin');
        $base = ['description' => 'Welcome', 'starts_at' => now()->addDay()->toDateTimeString(), 'ends_at' => now()->addDay()->addHours(2)->toDateTimeString(), 'location' => 'Kampala', 'capacity' => 30, 'status' => 'published'];
        $this->actingAs($admin)->get('/admin/events')->assertOk()->assertSee('create-event-tab-payment', false)->assertSee('Payment Amount (optional)');
        $this->post('/admin/events', $base + ['title' => 'Free event'])->assertSessionHasNoErrors();
        $this->post('/admin/events', $base + ['title' => 'Paid event', 'payment_amount' => '25000.50'])->assertSessionHasNoErrors();
        $this->post('/admin/events', $base + ['title' => 'Bad event', 'payment_amount' => '-5'])->assertSessionHasErrors('payment_amount');

        $free = DB::table('events')->where('title', 'Free event')->first();
        $paid = DB::table('events')->where('title', 'Paid event')->first();
        $this->assertNull($free->payment_amount);
        $this->assertEquals(25000.50, (float)$paid->payment_amount);

        auth()->logout();
        $this->get('/events/'.$paid->registration_token)->assertOk()->assertSee('Free during the free access period')->assertDontSee('How to pay');
        $this->endFreeAccess();
        $this->get('/events/'.$paid->registration_token)->assertOk()->assertSee('25,000.50')->assertSee('How to pay');
        $this->get('/events/'.$free->registration_token)->assertOk()->assertDontSee('How to pay');
    }
}
