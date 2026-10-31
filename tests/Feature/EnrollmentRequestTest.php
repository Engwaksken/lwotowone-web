<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Workflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EnrollmentRequestTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role='participant', array $extra=[]): User
    {
        return User::create(array_merge(['name'=>ucfirst($role),'email'=>uniqid().'@example.test','password'=>'Long-test-password','role'=>$role,'status'=>'active'],$extra));
    }

    private function course(string $title='Course', string $status='published'): int
    {
        $program=Workflow::insert('programs',['title'=>'Program','slug'=>uniqid(),'category'=>'TVET','summary'=>'Text','body'=>'Text','status'=>'published']);
        return Workflow::insert('courses',['program_id'=>$program,'title'=>$title,'summary'=>'Text','instructor_id'=>$this->user('instructor')->id,'duration_hours'=>1,'status'=>$status]);
    }

    private function cohort(): int
    {
        return DB::table('mel_cohorts')->insertGetId(['name'=>uniqid('Cohort'),'enrollment_category'=>'Youth','learner_number_prefix'=>'LW','learner_number_format'=>'{prefix}-{year}-{sequence}','sequence_padding'=>4,'next_sequence'=>1,'active'=>true,'created_at'=>now(),'updated_at'=>now()]);
    }

    /** A learner already enrolled (and numbered) in one course. */
    private function enrolledLearner(int $course, int $cohort): User
    {
        $learner=$this->user('participant',['profile_complete'=>true,'selected_course_id'=>$course]);
        app(\App\Services\Enrollment::class)->confirm($learner->id,$cohort,$this->user('manager')->id);
        return $learner->fresh();
    }

    public function test_learner_requests_another_course_and_manager_approves(): void
    {
        Notification::fake();
        $cohort=$this->cohort();$first=$this->course('First');$second=$this->course('Second course');
        $learner=$this->enrolledLearner($first,$cohort);

        $this->actingAs($learner)->get('/portal/learn')->assertOk()->assertSee('Request enrollment');
        $this->post('/portal/enrollment-requests',['course_id'=>$second,'message'=>'I want to learn more'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('enrollment_requests',['user_id'=>$learner->id,'course_id'=>$second,'status'=>'pending','message'=>'I want to learn more']);
        $this->get('/portal/learn')->assertSee('Request pending');
        $this->post('/portal/enrollment-requests',['course_id'=>$second])->assertSessionHasErrors('course_id');

        $request=DB::table('enrollment_requests')->first();
        $manager=$this->user('manager');
        $this->actingAs($manager)->get('/admin/enrollment')->assertOk()->assertSee('Enrollment requests')->assertSee('Second course')->assertSee('I want to learn more');
        $this->post("/admin/enrollment/requests/{$request->id}/approve",['cohort_id'=>$cohort])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('enrollment_requests',['id'=>$request->id,'status'=>'approved','decided_by'=>$manager->id]);
        $this->assertNotNull(DB::table('enrolments')->where('user_id',$learner->id)->where('course_id',$second)->value('payment_confirmed_at'));
        $this->assertSame($learner->learner_no,$learner->fresh()->learner_no);
        Notification::assertSentTo($learner,\App\Notifications\PlatformNotice::class);

        $this->post("/admin/enrollment/requests/{$request->id}/approve",['cohort_id'=>$cohort])->assertSessionHasErrors('enrollment');
    }

    public function test_manager_rejects_with_reason_and_learner_can_request_again(): void
    {
        Notification::fake();
        $cohort=$this->cohort();$first=$this->course('First');$second=$this->course('Second course');
        $learner=$this->enrolledLearner($first,$cohort);
        $this->actingAs($learner)->post('/portal/enrollment-requests',['course_id'=>$second])->assertSessionHasNoErrors();
        $request=DB::table('enrollment_requests')->first();

        $this->actingAs($this->user('manager'))->post("/admin/enrollment/requests/{$request->id}/reject",['decision_note'=>'Cohort is full'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('enrollment_requests',['id'=>$request->id,'status'=>'rejected','decision_note'=>'Cohort is full']);
        $this->assertDatabaseMissing('enrolments',['user_id'=>$learner->id,'course_id'=>$second]);
        Notification::assertSentTo($learner,\App\Notifications\PlatformNotice::class);

        $this->actingAs($learner)->get('/portal/learn')->assertSee('Request declined')->assertSee('Cohort is full')->assertSee('Request again');
        $this->post('/portal/enrollment-requests',['course_id'=>$second])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('enrollment_requests',['id'=>$request->id,'status'=>'pending','decision_note'=>null]);
        $this->assertDatabaseCount('enrollment_requests',1);
    }

    public function test_requests_are_validated_and_admin_actions_require_manager(): void
    {
        $cohort=$this->cohort();$first=$this->course('First');$draft=$this->course('Draft','draft');
        $learner=$this->enrolledLearner($first,$cohort);
        $this->actingAs($learner)->post('/portal/enrollment-requests',['course_id'=>$first])->assertSessionHasErrors('course_id');
        $this->post('/portal/enrollment-requests',['course_id'=>$draft])->assertSessionHasErrors('course_id');
        $this->assertDatabaseCount('enrollment_requests',0);

        $other=$this->course('Other');
        $this->post('/portal/enrollment-requests',['course_id'=>$other])->assertSessionHasNoErrors();
        $request=DB::table('enrollment_requests')->first();
        $this->post("/admin/enrollment/requests/{$request->id}/approve",['cohort_id'=>$cohort])->assertForbidden();
        $this->post("/admin/enrollment/requests/{$request->id}/reject")->assertForbidden();
        $this->actingAs($this->user('instructor'))->post('/portal/enrollment-requests',['course_id'=>$other])->assertForbidden();
    }
}
