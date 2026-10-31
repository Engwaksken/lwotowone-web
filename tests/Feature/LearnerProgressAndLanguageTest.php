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

    public function test_assignments_and_resources_belong_to_one_language(): void
    {
        $course=$this->course();
        $this->lesson($course,1,'en');$lgLesson=$this->lesson($course,2,'lg');
        $enTask=Workflow::insert('assignments',['course_id'=>$course,'title'=>'English task','instructions'=>'Do it','pass_mark'=>50,'content_language'=>'en','status'=>'published']);
        $lgTask=Workflow::insert('assignments',['course_id'=>$course,'title'=>null,'instructions'=>null,'title_lg'=>'Omulimu','instructions_lg'=>'Kola','pass_mark'=>50,'content_language'=>'lg','status'=>'published']);
        Workflow::insert('resources',['course_id'=>$course,'title'=>'English guide','description'=>'Guide','content_language'=>'en','status'=>'published']);
        $lgResource=Workflow::insert('resources',['course_id'=>$course,'title'=>null,'description'=>null,'title_lg'=>'Ekitabo','description_lg'=>'Ebikwata','content_language'=>'lg','lesson_id'=>$lgLesson,'status'=>'published']);

        $english=$this->learner($course,'en');
        $this->actingAs($english)->get("/learning/$course")->assertOk()->assertSee('English task')->assertSee('English guide')->assertDontSee('Omulimu')->assertDontSee('Ekitabo');
        $this->post('/actions/submit',['assignment_id'=>$lgTask,'body'=>'Answer'])->assertForbidden();
        $this->get("/learning/content/resources/$lgResource")->assertForbidden();

        $luganda=$this->learner($course,'lg');
        $this->actingAs($luganda)->get("/learning/$course")->assertOk()->assertSee('Omulimu')->assertSee('Ekitabo')->assertDontSee('English task')->assertDontSee('English guide');
        $this->post('/actions/submit',['assignment_id'=>$enTask,'body'=>'Answer'])->assertForbidden();
        // Completion counts only the learner's own language: one Luganda lesson and one Luganda assignment.
        $this->post('/actions/complete',['lesson_id'=>$lgLesson])->assertRedirect();
        DB::table('submissions')->insert(['user_id'=>$luganda->id,'assignment_id'=>$lgTask,'body'=>'Answer','status'=>'passed','created_at'=>now(),'updated_at'=>now()]);
        $this->assertTrue(LearningAccess::completionReady($luganda,$course));
    }

    public function test_admin_creates_luganda_assignment_and_resource_without_english_fields(): void
    {
        $course=$this->course();$enLesson=$this->lesson($course,1,'en');$lgLesson=$this->lesson($course,2,'lg');
        $admin=$this->user('admin');
        $this->actingAs($admin)->get('/admin/assignments')->assertOk()->assertSee('Assignment content language');
        $this->post('/admin/assignments',['course_id'=>$course,'assessment_type'=>'assignment','lesson_language'=>'lg','title_lg'=>'Omulimu','instructions_lg'=>'Kola','pass_mark'=>60,'status'=>'published'])->assertRedirect()->assertSessionHasNoErrors();
        $task=DB::table('assignments')->latest('id')->first();
        $this->assertSame('lg',$task->content_language);$this->assertNull($task->title);$this->assertSame('Omulimu',$task->title_lg);
        $this->post('/admin/assignments',['course_id'=>$course,'assessment_type'=>'assignment','lesson_language'=>'lg','pass_mark'=>60,'status'=>'published'])->assertSessionHasErrors(['title_lg','instructions_lg']);

        $resource=['course_id'=>$course,'lesson_language'=>'lg','title_lg'=>'Ekitabo','description_lg'=>'Ebikwata','content_format'=>'text','content_source'=>'write','content_body_lg'=>'Ebirimu','status'=>'published'];
        $this->post('/admin/resources',$resource+['lesson_id'=>$enLesson])->assertSessionHasErrors('lesson_id');
        $this->post('/admin/resources',$resource+['lesson_id'=>$lgLesson])->assertRedirect()->assertSessionHasNoErrors();
        $saved=DB::table('resources')->latest('id')->first();
        $this->assertSame('lg',$saved->content_language);$this->assertNull($saved->title);$this->assertSame('Ebirimu',$saved->content_body_lg);
        $this->get('/admin/resources')->assertOk()->assertSee('Ekitabo')->assertSee('Luganda');
    }

    public function test_migration_splits_existing_luganda_translations_into_their_own_items(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $migration=require database_path('migrations/2026_10_31_000002_add_content_language_to_assignments_and_resources.php');
        $migration->down();
        $course=$this->course();$enLesson=$this->lesson($course,1,'en');
        $task=DB::table('assignments')->insertGetId(['course_id'=>$course,'assessment_type'=>'assignment','title'=>'Task','instructions'=>'Do it','title_lg'=>'Omulimu','instructions_lg'=>'Kola','pass_mark'=>50,'status'=>'published','created_at'=>now(),'updated_at'=>now()]);
        $partial=DB::table('assignments')->insertGetId(['course_id'=>$course,'assessment_type'=>'assignment','title'=>'Partial','instructions'=>'Do it','title_lg'=>'Kitundu','pass_mark'=>50,'status'=>'published','created_at'=>now(),'updated_at'=>now()]);
        DB::table('learning_attachments')->insert(['entity_type'=>'assignment','entity_id'=>$task,'file_path'=>'attachments/sheet.pdf','original_name'=>'sheet.pdf','created_at'=>now(),'updated_at'=>now()]);
        \Illuminate\Support\Facades\Storage::disk('local')->put('resources/guide.pdf','PDF');
        $resource=DB::table('resources')->insertGetId(['course_id'=>$course,'lesson_id'=>$enLesson,'title'=>'Guide','description'=>'About','title_lg'=>'Ekitabo','description_lg'=>'Ebikwata','file_path'=>'resources/guide.pdf','content_format'=>'file','content_source'=>'upload','status'=>'published','created_at'=>now(),'updated_at'=>now()]);

        $migration->up();

        $this->assertDatabaseHas('assignments',['id'=>$task,'content_language'=>'en','title'=>'Task','title_lg'=>null,'instructions_lg'=>null]);
        $lgTask=DB::table('assignments')->where('content_language','lg')->first();
        $this->assertSame(['Omulimu','Kola',null,null],[$lgTask->title_lg,$lgTask->instructions_lg,$lgTask->title,$lgTask->instructions]);
        $this->assertSame(1,DB::table('learning_attachments')->where('entity_type','assignment')->where('entity_id',$lgTask->id)->count());
        // An incomplete translation is left on the English item rather than becoming a half-empty Luganda one.
        $this->assertDatabaseHas('assignments',['id'=>$partial,'content_language'=>'en','title_lg'=>'Kitundu']);
        $this->assertSame(1,DB::table('assignments')->where('content_language','lg')->count());

        $this->assertDatabaseHas('resources',['id'=>$resource,'content_language'=>'en','title_lg'=>null,'lesson_id'=>$enLesson]);
        $lgResource=DB::table('resources')->where('content_language','lg')->first();
        $this->assertSame(['Ekitabo',null,null],[$lgResource->title_lg,$lgResource->title,$lgResource->lesson_id]);
        $this->assertNotSame('resources/guide.pdf',$lgResource->file_path);
        \Illuminate\Support\Facades\Storage::disk('local')->assertExists([$lgResource->file_path,'resources/guide.pdf']);
    }

    public function test_course_cannot_be_completed_while_assignments_exist_only_in_another_language(): void
    {
        $course=$this->course();$lgLesson=$this->lesson($course,1,'lg');
        Workflow::insert('assignments',['course_id'=>$course,'title'=>'English task','instructions'=>'Do it','pass_mark'=>50,'content_language'=>'en','status'=>'published']);
        $learner=$this->learner($course,'lg');
        $this->actingAs($learner)->post('/actions/complete',['lesson_id'=>$lgLesson])->assertRedirect();
        $this->assertFalse(LearningAccess::completionReady($learner,$course));
        $this->get('/portal/learn')->assertSee('Practical assignments in your learning language are being prepared');
        $this->actingAs($this->user('manager'))->get('/admin/enrollment')->assertSee('No assignments in Luganda yet');

        Workflow::insert('assignments',['course_id'=>$course,'title_lg'=>'Omulimu','instructions_lg'=>'Kola','pass_mark'=>50,'content_language'=>'lg','status'=>'published']);
        $this->assertFalse(LearningAccess::completionReady($learner,$course));
        DB::table('submissions')->insert(['user_id'=>$learner->id,'assignment_id'=>DB::table('assignments')->where('content_language','lg')->value('id'),'body'=>'Answer','status'=>'passed','created_at'=>now(),'updated_at'=>now()]);
        $this->assertTrue(LearningAccess::completionReady($learner,$course));
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
