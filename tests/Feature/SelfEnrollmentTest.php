<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Workflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SelfEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role='participant', array $extra=[]): User
    {
        return User::create(array_merge(['name'=>ucfirst($role).' '.uniqid(),'email'=>uniqid().'@example.test','password'=>'Long-test-password','role'=>$role,'status'=>'active'],$extra));
    }

    private function course(string $title='Course', string $status='published', ?User $instructor=null): int
    {
        $instructor ??= $this->user('instructor');
        $program=Workflow::insert('programs',['title'=>'Program','slug'=>uniqid(),'category'=>'TVET','summary'=>'Text','body'=>'Text','status'=>'published']);
        $id=Workflow::insert('courses',['program_id'=>$program,'title'=>$title,'summary'=>'Text','instructor_id'=>$instructor->id,'duration_hours'=>1,'status'=>$status]);
        DB::table('course_instructors')->insertOrIgnore(['course_id'=>$id,'user_id'=>$instructor->id,'created_at'=>now(),'updated_at'=>now()]);
        return $id;
    }

    private function cohort(string $name='Cohort'): int
    {
        return DB::table('mel_cohorts')->insertGetId(['name'=>uniqid($name),'enrollment_category'=>'Youth','learner_number_prefix'=>'LW','learner_number_format'=>'{prefix}-{year}-{sequence}','sequence_padding'=>4,'next_sequence'=>1,'active'=>true,'created_at'=>now(),'updated_at'=>now()]);
    }

    /** A learner already enrolled (paid and numbered) in one course. */
    private function enrolledLearner(int $course, int $cohort): User
    {
        $learner=$this->user('participant',['profile_complete'=>true,'selected_course_id'=>$course]);
        app(\App\Services\Enrollment::class)->confirm($learner->id,$cohort,$this->user('manager')->id);
        return $learner->fresh();
    }

    public function test_learner_enrolls_themselves_in_one_course_per_cohort(): void
    {
        $first=$this->cohort('First');$second=$this->cohort('Second');
        $tailoring=$this->course('Tailoring');$poultry=$this->course('Poultry');$baking=$this->course('Baking');
        $learner=$this->enrolledLearner($tailoring,$first);
        $this->assertSame($first,(int)DB::table('enrolments')->where('user_id',$learner->id)->value('cohort_id'));

        // Only the cohort without a course is offered.
        $this->actingAs($learner)->get('/portal/learn')->assertOk()->assertSee('action="/portal/enrollments"',false)->assertSee('one course in each cohort')->assertSee('name="cohort_id" value="'.$second.'"',false);
        $this->post('/portal/enrollments',['course_id'=>$poultry,'cohort_id'=>$first])->assertSessionHasErrors('cohort_id');
        $this->post('/portal/enrollments',['course_id'=>$poultry,'cohort_id'=>$second])->assertRedirect('/portal/learn')->assertSessionHasNoErrors();

        $row=DB::table('enrolments')->where('user_id',$learner->id)->where('course_id',$poultry)->first();
        $this->assertSame($second,(int)$row->cohort_id);
        $this->assertNull($row->trial_started_at,'A learner who already paid for access starts straight away.');
        $this->assertTrue(\App\Services\LearningAccess::allowed($learner->fresh(),$poultry));
        $this->assertSame($tailoring,(int)$learner->fresh()->selected_course_id);

        // Both open cohorts are now used.
        $this->post('/portal/enrollments',['course_id'=>$baking,'cohort_id'=>$second])->assertSessionHasErrors('cohort_id');
        $this->post('/portal/enrollments',['course_id'=>$poultry,'cohort_id'=>$second])->assertSessionHasErrors('course_id');
        $this->get('/portal/learn')->assertSee('You already have a course in every open cohort');
        $this->assertSame(2,DB::table('enrolments')->where('user_id',$learner->id)->count());
    }

    public function test_mobile_app_enrolls_with_a_trial_and_sees_cohorts(): void
    {
        $cohort=$this->cohort();$course=$this->course('Tailoring');$other=$this->course('Poultry');$draft=$this->course('Draft','draft');
        $learner=$this->user('participant',['profile_complete'=>true]);
        $auth=['Authorization'=>'Bearer '.$learner->createToken('app')->plainTextToken];

        $this->withHeaders($auth)->postJson('/api/enrollments',['course_id'=>$course])->assertUnprocessable()->assertJsonValidationErrors('cohort_id');
        $this->withHeaders($auth)->postJson('/api/enrollments',['course_id'=>$draft,'cohort_id'=>$cohort])->assertUnprocessable()->assertJsonValidationErrors('course_id');
        $this->withHeaders($auth)->postJson('/api/enrollments',['course_id'=>$course,'cohort_id'=>$cohort])->assertCreated();
        $this->withHeaders($auth)->postJson('/api/enrollments',['course_id'=>$other,'cohort_id'=>$cohort])->assertUnprocessable()->assertJsonValidationErrors('cohort_id');

        $row=DB::table('enrolments')->where('user_id',$learner->id)->first();
        $this->assertNotNull($row->trial_expires_at);
        $this->assertSame($course,(int)$learner->fresh()->selected_course_id);
        $snapshot=$this->withHeaders($auth)->getJson('/api/snapshot')->assertOk();
        $this->assertSame([$cohort],collect($snapshot->json('cohorts'))->pluck('id')->all());
        $this->assertSame($cohort,(int)$snapshot->json('enrolments.0.cohort_id'));

        $this->actingAs($this->user('instructor'))->postJson('/portal/enrollments',['course_id'=>$course,'cohort_id'=>$cohort])->assertForbidden();
    }

    public function test_learner_cannot_change_their_own_course(): void
    {
        $cohort=$this->cohort();$course=$this->course('Tailoring');$other=$this->course('Poultry');
        $learner=$this->user('participant',['profile_complete'=>true,'selected_course_id'=>$course]);
        $this->actingAs($learner)->post('/portal/enrollments',['course_id'=>$course,'cohort_id'=>$cohort])->assertSessionHasNoErrors();

        $profile=['name'=>'Learner','phone'=>'+256700000000','gender'=>'Female','location'=>'Kampala','urban_rural'=>'Urban','learner_age'=>22,'refugee'=>0,'pwd'=>0,'education_level'=>'O-Level','employed'=>0];
        $this->post('/profile',$profile+['selected_course_id'=>$other])->assertStatus(422);
        $this->post('/profile',$profile+['selected_course_id'=>$course])->assertRedirect();
        $this->assertSame($course,(int)$learner->fresh()->selected_course_id);
        $this->get('/profile')->assertSee('Only an administrator or instructor can change this course');

        $this->put("/admin/enrollment/learners/{$learner->id}/courses/$course",['course_id'=>$other])->assertForbidden();
        $this->assertDatabaseHas('enrolments',['user_id'=>$learner->id,'course_id'=>$course]);
    }

    public function test_instructors_change_courses_they_teach_and_admins_change_any(): void
    {
        Notification::fake();
        $instructor=$this->user('instructor');
        $cohort=$this->cohort();$mine=$this->course('Mine','published',$instructor);$alsoMine=$this->course('Also mine','published',$instructor);$theirs=$this->course('Theirs');
        $learner=$this->enrolledLearner($mine,$cohort);

        $this->actingAs($instructor)->get('/admin/learner-progress')->assertOk()->assertSee($learner->email)->assertSee('Change course');
        $this->put("/admin/enrollment/learners/{$learner->id}/courses/$mine",['course_id'=>$theirs])->assertForbidden();
        $this->put("/admin/enrollment/learners/{$learner->id}/courses/$mine",['course_id'=>$alsoMine])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('enrolments',['user_id'=>$learner->id,'course_id'=>$alsoMine,'cohort_id'=>$cohort]);
        Notification::assertSentTo($learner,\App\Notifications\PlatformNotice::class);

        // Instructors only see and move learners in their own courses.
        $outsider=$this->enrolledLearner($theirs,$cohort);
        $this->get('/admin/learner-progress')->assertDontSee($outsider->email);
        $this->put("/admin/enrollment/learners/{$outsider->id}/courses/$theirs",['course_id'=>$mine])->assertForbidden();

        $this->actingAs($this->user('admin'))->put("/admin/enrollment/learners/{$learner->id}/courses/$alsoMine",['course_id'=>$theirs])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('enrolments',['user_id'=>$learner->id,'course_id'=>$theirs,'cohort_id'=>$cohort]);
        $this->actingAs($this->user('mentor'))->put("/admin/enrollment/learners/{$learner->id}/courses/$theirs",['course_id'=>$mine])->assertForbidden();
    }

    public function test_admin_enrollment_also_keeps_one_course_per_cohort(): void
    {
        $cohort=$this->cohort();$next=$this->cohort('Next');$first=$this->course('First');$second=$this->course('Second');
        $learner=$this->enrolledLearner($first,$cohort);
        $this->actingAs($this->user('manager'))->post("/admin/enrollment/learners/{$learner->id}/confirm-payment",['cohort_id'=>$cohort,'course_id'=>$second])->assertSessionHasErrors('enrollment');
        $this->assertDatabaseMissing('enrolments',['user_id'=>$learner->id,'course_id'=>$second]);
        $this->post("/admin/enrollment/learners/{$learner->id}/confirm-payment",['cohort_id'=>$next,'course_id'=>$second])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('enrolments',['user_id'=>$learner->id,'course_id'=>$second,'cohort_id'=>$next]);
        $this->delete("/admin/enrollment/cohorts/$next")->assertStatus(422);
    }

    public function test_requests_sent_before_self_enrollment_can_still_be_decided(): void
    {
        Notification::fake();
        $cohort=$this->cohort();$next=$this->cohort('Next');$first=$this->course('First');$second=$this->course('Second');$third=$this->course('Third');
        $learner=$this->enrolledLearner($first,$cohort);
        $pending=fn($course)=>DB::table('enrollment_requests')->insertGetId(['user_id'=>$learner->id,'course_id'=>$course,'status'=>'pending','created_at'=>now(),'updated_at'=>now()]);
        $request=$pending($second);

        $this->actingAs($this->user('manager'))->get('/admin/enrollment')->assertOk()->assertSee('Enrollment requests')->assertSee('Second');
        $this->post("/admin/enrollment/requests/$request/approve",['cohort_id'=>$cohort])->assertSessionHasErrors('enrollment');
        $this->post("/admin/enrollment/requests/$request/approve",['cohort_id'=>$next])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('enrollment_requests',['id'=>$request,'status'=>'approved']);

        // Enrolling directly settles a learner's old pending request for that course.
        $old=$pending($third);$later=$this->cohort('Later');
        $this->actingAs($learner)->post('/portal/enrollments',['course_id'=>$third,'cohort_id'=>$later])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('enrollment_requests',['id'=>$old,'status'=>'approved']);
        $this->post('/portal/enrollment-requests',['course_id'=>$third])->assertStatus(405);
    }

    private function signupPayload(array $overrides=[]): array
    {
        return $overrides+['name'=>'New Learner','email'=>'learner-'.uniqid().'@example.test','phone'=>'+256700000001','password'=>'A-long-test-password','password_confirmation'=>'A-long-test-password','consent'=>1,'terms_read'=>1,'privacy_read'=>1];
    }

    private function profilePayload(int $course): array
    {
        return ['name'=>'New Learner','phone'=>'+256700000001','selected_course_id'=>$course,'gender'=>'Female','location'=>'Kampala','urban_rural'=>'Urban','learner_age'=>22,'refugee'=>0,'pwd'=>0,'education_level'=>'O-Level','employed'=>0];
    }

    public function test_course_chosen_at_signup_is_enrolled_once_the_profile_is_complete(): void
    {
        Notification::fake();
        $course=$this->course('Chosen course');$draft=$this->course('Draft course','draft');$cohort=$this->cohort();
        $this->get('/register?course='.$course)->assertOk()->assertSee('Chosen course')->assertDontSee('Draft course')->assertSee('value="'.$course.'" selected',false);
        $this->post('/register',$this->signupPayload())->assertSessionHasErrors('selected_course_id');
        $this->post('/register',$this->signupPayload(['selected_course_id'=>$draft]))->assertSessionHasErrors('selected_course_id');
        $this->post('/register',$this->signupPayload(['email'=>'chooser@example.test','selected_course_id'=>$course]))->assertRedirect('/login')->assertSessionHasNoErrors();

        $learner=User::where('email','chooser@example.test')->firstOrFail();
        $this->assertSame($course,(int)$learner->selected_course_id);
        $this->assertDatabaseMissing('enrolments',['user_id'=>$learner->id]);

        $learner->forceFill(['email_verified_at'=>now(),'requires_email_verification'=>false])->save();
        $this->actingAs($learner)->post('/profile',$this->profilePayload($course))->assertRedirect('/portal/learn')->assertSessionHas('success','Learner profile saved. You are enrolled in Chosen course.');
        $this->assertDatabaseHas('enrolments',['user_id'=>$learner->id,'course_id'=>$course,'cohort_id'=>$cohort]);
        $this->assertDatabaseHas('users',['id'=>$learner->id,'learner_status'=>'Active','profile_complete'=>true]);

        // Saving the profile again does not enroll a second time.
        $this->post('/profile',$this->profilePayload($course))->assertSessionHasNoErrors();
        $this->assertSame(1,DB::table('enrolments')->where('user_id',$learner->id)->count());
    }

    public function test_profile_still_saves_when_no_cohort_is_open_for_the_chosen_course(): void
    {
        $course=$this->course('Waiting course');
        $learner=$this->user('participant',['selected_course_id'=>$course]);
        $this->actingAs($learner)->post('/profile',$this->profilePayload($course))->assertRedirect()->assertSessionHas('success',fn($m)=>str_contains($m,'There is no open cohort yet'));
        $this->assertDatabaseHas('users',['id'=>$learner->id,'profile_complete'=>true]);
        $this->assertDatabaseMissing('enrolments',['user_id'=>$learner->id]);
    }
}
