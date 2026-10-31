<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\LearningAccess;
use App\Services\Workflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class LearnerProgressAndLanguageTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role='participant', array $extra=[]): User
    {
        return User::create(array_merge(['name'=>ucfirst($role),'email'=>uniqid().'@example.test','password'=>'Long-test-password','role'=>$role,'status'=>'active'],$extra));
    }

    private function course(string $title='Course'): int
    {
        $program=Workflow::insert('programs',['title'=>'Program','slug'=>uniqid(),'category'=>'TVET','summary'=>'Text','body'=>'Text','status'=>'published']);
        return Workflow::insert('courses',['program_id'=>$program,'title'=>$title,'summary'=>'Text','instructor_id'=>$this->user('instructor')->id,'duration_hours'=>1,'status'=>'published']);
    }

    private function lesson(int $course, int $position, string $language): int
    {
        $text=$language==='lg'?['title'=>null,'body'=>null,'title_lg'=>'Essomo '.$position,'body_lg'=>'Ebirimu '.$position]:['title'=>'English lesson '.$position,'body'=>'English body '.$position];
        return Workflow::insert('lessons',$text+['course_id'=>$course,'position'=>$position,'content_language'=>$language,'status'=>'published']);
    }

    private function cohort(): int
    {
        return DB::table('mel_cohorts')->insertGetId(['name'=>uniqid('Cohort'),'enrollment_category'=>'Youth','learner_number_prefix'=>'LW','learner_number_format'=>'{prefix}-{year}-{sequence}','sequence_padding'=>4,'next_sequence'=>1,'active'=>true,'created_at'=>now(),'updated_at'=>now()]);
    }

    private function learner(int $course, string $language='en'): User
    {
        $learner=$this->user('participant',['name'=>'Learner '.uniqid(),'profile_complete'=>true,'selected_course_id'=>$course,'preferred_language'=>$language]);
        app(\App\Services\Enrollment::class)->confirm($learner->id,$this->cohort(),$this->user('manager')->id);
        return $learner->fresh();
    }

    public function test_learners_only_see_and_complete_lessons_in_their_language(): void
    {
        $course=$this->course();
        $en1=$this->lesson($course,1,'en');$lg1=$this->lesson($course,2,'lg');$en2=$this->lesson($course,3,'en');

        $english=$this->learner($course,'en');
        $this->actingAs($english)->get("/learning/$course")->assertOk()->assertSee('English lesson 1')->assertSee('English lesson 3')->assertDontSee('Essomo 2');
        $this->post('/actions/complete',['lesson_id'=>$lg1])->assertForbidden();
        $this->post('/actions/complete',['lesson_id'=>$en1])->assertRedirect();
        // The Luganda lesson between them does not block the next English lesson.
        $this->post('/actions/complete',['lesson_id'=>$en2])->assertRedirect();
        $this->assertTrue(LearningAccess::lessonsComplete($english,$course));

        $luganda=$this->learner($course,'lg');
        $this->actingAs($luganda)->get("/learning/$course")->assertOk()->assertSee('Essomo 2')->assertDontSee('English lesson 1');
        $this->post('/actions/complete',['lesson_id'=>$en1])->assertForbidden();
        $this->post('/actions/complete',['lesson_id'=>$lg1])->assertRedirect();
        $this->assertTrue(LearningAccess::lessonsComplete($luganda,$course));
    }

    public function test_manager_sees_lesson_progress_per_learner(): void
    {
        $course=$this->course('Poultry basics');
        $en1=$this->lesson($course,1,'en');$this->lesson($course,2,'en');$this->lesson($course,3,'lg');
        $learner=$this->learner($course,'en');
        $this->actingAs($learner)->post('/actions/complete',['lesson_id'=>$en1])->assertRedirect();

        $this->actingAs($this->user('manager'))->get('/admin/enrollment')->assertOk()
            ->assertSee('Learner progress')->assertSee($learner->name)->assertSee('Poultry basics')
            ->assertSee('1 of 2 · 50%')->assertSee('English lesson 1')->assertSee('Completed '.now()->format('d M Y'))->assertSee('Not started');
    }

    public function test_manager_can_change_learner_course(): void
    {
        Notification::fake();
        $first=$this->course('First');$second=$this->course('Second');$third=$this->course('Third');
        $learner=$this->learner($first);
        $manager=$this->user('manager');

        $this->actingAs($manager)->put("/admin/enrollment/learners/{$learner->id}/courses/$first",['course_id'=>$second])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('enrolments',['user_id'=>$learner->id,'course_id'=>$first]);
        $this->assertNotNull(DB::table('enrolments')->where('user_id',$learner->id)->where('course_id',$second)->value('payment_confirmed_at'));
        $this->assertDatabaseHas('users',['id'=>$learner->id,'selected_course_id'=>$second]);
        $this->assertDatabaseHas('audit_logs',['action'=>'change-course','record_id'=>$learner->id]);
        Notification::assertSentTo($learner,\App\Notifications\PlatformNotice::class);

        // Cannot move into a course they already have, or move a course that has a certificate.
        app(\App\Services\Enrollment::class)->confirm($learner->id,$this->cohort(),$manager->id,$third);
        $this->put("/admin/enrollment/learners/{$learner->id}/courses/$second",['course_id'=>$third])->assertSessionHasErrors('course_id');
        DB::table('course_certificates')->insert(['user_id'=>$learner->id,'course_id'=>$second,'reference'=>'CERT-1','recommended_by'=>$manager->id,'recommended_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
        $this->put("/admin/enrollment/learners/{$learner->id}/courses/$second",['course_id'=>$first])->assertSessionHasErrors('course_id');

        $this->actingAs($learner)->put("/admin/enrollment/learners/{$learner->id}/courses/$second",['course_id'=>$first])->assertForbidden();
    }
}
