<?php
namespace Tests\Feature;

use App\Models\User;
use App\Services\{Workflow,LearningAccess,Enrollment,Snapshot,CertificatePdf};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB,Storage};
use Tests\TestCase;

class LearnerJourneyTest extends TestCase
{
    use RefreshDatabase;
    private function user(string $role='participant',array $extra=[]): User {return User::create(array_merge(['name'=>'Test '.$role,'email'=>uniqid().'@example.test','password'=>'Long-test-password','role'=>$role,'status'=>'active','profile_complete'=>true],$extra));}
    private function course(?User $teacher=null): int {$teacher??=$this->user('instructor');$program=Workflow::insert('programs',['title'=>'Program','slug'=>uniqid(),'summary'=>'Text','body'=>'Text','category'=>'TVET','status'=>'published']);$id=Workflow::insert('courses',['title'=>'Course','program_id'=>$program,'instructor_id'=>$teacher->id,'summary'=>'Text','duration_hours'=>10,'status'=>'published']);Workflow::insert('course_instructors',['course_id'=>$id,'user_id'=>$teacher->id]);return $id;}
    private function lesson(int $course,int $position,?int $module=null): int {return Workflow::insert('lessons',['course_id'=>$course,'title'=>'Lesson '.$position,'position'=>$position,'module_id'=>$module,'body'=>'Private body '.$position,'video_url'=>'https://youtu.be/abcdefghijk','status'=>'published']);}
    private function createCall(int $course): object {$id=Workflow::insert('application_calls',['token'=>(string)\Illuminate\Support\Str::uuid(),'title'=>'Open skills call','description'=>'Apply for training','course_id'=>$course,'opens_at'=>now()->subHour(),'closes_at'=>now()->addDay(),'status'=>'published','created_by'=>$this->user('manager')->id]);return DB::table('application_calls')->find($id);}
    private function cohort(): int {return Workflow::insert('mel_cohorts',['name'=>'Cohort','enrollment_category'=>'Youth','learner_number_prefix'=>'LW','learner_number_format'=>'{prefix}-{year}-{sequence}','sequence_padding'=>4,'next_sequence'=>1,'active'=>true]);}

    public function test_call_application_and_me_approval_create_one_nonrenewable_twelve_hour_trial(): void {
        $course=$this->course();$learner=$this->user('participant',['selected_course_id'=>$course]);$manager=$this->user('manager');
        $this->actingAs($manager)->post('/admin/calls',['title'=>'Skills call','description'=>'Training','course_id'=>$course,'opens_at'=>now()->subHour()->toDateTimeString(),'closes_at'=>now()->addDay()->toDateTimeString(),'status'=>'published'])->assertRedirect()->assertSessionHasNoErrors();
        $call=DB::table('application_calls')->first();
        $this->get('/admin/calls/'.$call->id.'/qr.svg')->assertOk()->assertHeader('Content-Type','image/svg+xml')->assertSee('<svg',false);
        $this->actingAs($learner)->get('/calls/'.$call->token)->assertOk()->assertSee('Submit application');
        $this->post('/calls/'.$call->token.'/apply',['motivation'=>'I want to learn'])->assertRedirect();
        $this->assertDatabaseCount('enrolments',0);
        $this->post('/calls/'.$call->token.'/apply',['motivation'=>'Again'])->assertUnprocessable();
        $application=DB::table('call_applications')->first();
        $this->actingAs($manager)->put('/admin/calls/applications/'.$application->id,['status'=>'approved'])->assertRedirect();
        $enrollment=DB::table('enrolments')->first();$this->assertSame(12,(int)\Carbon\Carbon::parse($enrollment->trial_started_at)->diffInHours($enrollment->trial_expires_at));$this->assertNull($enrollment->payment_confirmed_at);
        $this->travel(1)->hours();$this->put('/admin/calls/applications/'.$application->id,['status'=>'approved'])->assertRedirect();
        $this->assertDatabaseHas('enrolments',['id'=>$enrollment->id,'trial_expires_at'=>$enrollment->trial_expires_at]);$this->assertDatabaseCount('enrolments',1);
    }

    public function test_modules_lessons_and_private_resource_urls_unlock_sequentially_without_snapshot_leaks(): void {
        Storage::fake('local');$course=$this->course();$learner=$this->user();LearningAccess::trial($learner->id,$course);
        $firstModule=Workflow::insert('course_modules',['course_id'=>$course,'title'=>'Module one','position'=>1,'status'=>'published']);$secondModule=Workflow::insert('course_modules',['course_id'=>$course,'title'=>'Module two','position'=>2,'status'=>'published']);
        $first=$this->lesson($course,1,$firstModule);$second=$this->lesson($course,1,$secondModule);
        $draftModule=Workflow::insert('course_modules',['course_id'=>$course,'title'=>'Draft module','position'=>3,'status'=>'draft']);$draft=$this->lesson($course,99,$draftModule);
        $resource=Workflow::insert('resources',['course_id'=>$course,'lesson_id'=>$second,'title'=>'Second resource','description'=>'Text','file_path'=>'resources/private.txt','status'=>'published']);Storage::disk('local')->put('resources/private.txt','Private resource');
        $snapshot=Snapshot::get($learner);$this->assertNull($snapshot['lessons']->firstWhere('id',$second)->body);$this->assertNull($snapshot['lessons']->firstWhere('id',$draft));$this->assertNull($snapshot['resources']->first()->viewer_url);
        $this->actingAs($learner)->postJson('/actions/complete',['lesson_id'=>$second])->assertForbidden();$this->get('/learning/content/resources/'.$resource)->assertForbidden();
        $this->get('/learning/'.$course)->assertOk()->assertSee('Module one')->assertSee('Module two')->assertSee('Private body 1')->assertDontSee('Draft module');
        $this->postJson('/actions/complete',['lesson_id'=>$first])->assertRedirect();$this->get('/learning/content/resources/'.$resource)->assertOk();$this->postJson('/actions/complete',['lesson_id'=>$second])->assertRedirect();
    }

    public function test_trial_expires_at_exact_boundary_and_course_scoped_payment_does_not_unlock_other_trials(): void {
        Storage::fake('local');$first=$this->course();$second=$this->course();$learner=$this->user('participant',['selected_course_id'=>$first]);LearningAccess::trial($learner->id,$first);LearningAccess::trial($learner->id,$second);$lesson=$this->lesson($first,1);
        $resource=Workflow::insert('resources',['course_id'=>$first,'title'=>'Resource','description'=>'Text','file_path'=>'resources/a.txt','status'=>'published']);Storage::disk('local')->put('resources/a.txt','Secret');
        $this->travel(12)->hours();$this->assertFalse(LearningAccess::allowed($learner,$first));$this->actingAs($learner)->get('/learning/'.$first)->assertOk()->assertDontSee('Private body 1');$this->get('/learning/content/resources/'.$resource)->assertForbidden();$this->postJson('/actions/complete',['lesson_id'=>$lesson])->assertForbidden();
        app(Enrollment::class)->confirm($learner->id,$this->cohort(),$this->user('manager')->id,$first);$learner->refresh();$this->assertTrue(LearningAccess::allowed($learner,$first));$this->assertFalse(LearningAccess::allowed($learner,$second));$this->get('/learning/content/resources/'.$resource)->assertOk();
        $this->assertNull(Snapshot::get($learner)['lessons']->where('course_id',$second)->first()?->body);
    }

    public function test_assigned_instructor_recommendation_is_required_and_owner_can_download_real_pdf(): void {
        Storage::fake('local');$teacher=$this->user('instructor');$course=$this->course($teacher);$learner=$this->user();LearningAccess::trial($learner->id,$course);$lesson=$this->lesson($course,1);
        $this->actingAs($teacher)->post('/admin/certificates/'.$course.'/recommend',['user_id'=>$learner->id])->assertUnprocessable();Workflow::run($learner,'complete',['lesson_id'=>$lesson]);
        $this->actingAs($learner)->get('/certificates/'.$course)->assertForbidden();$this->actingAs($this->user('instructor'))->post('/admin/certificates/'.$course.'/recommend',['user_id'=>$learner->id])->assertForbidden();
        $this->actingAs($teacher)->post('/admin/certificates/'.$course.'/recommend',['user_id'=>$learner->id])->assertRedirect();
        $this->actingAs($learner)->get('/certificates/'.$course)->assertOk()->assertSee('Download certificate PDF');$response=$this->get('/certificates/'.$course.'/file?download=1')->assertOk()->assertHeader('Content-Type','application/pdf');
        $this->assertStringStartsWith('%PDF-',$response->getFile()->getContent());$certificate=DB::table('course_certificates')->first();Storage::disk('local')->assertExists($certificate->file_path);
        $this->actingAs($this->user())->get('/certificates/'.$course.'/file')->assertForbidden();$this->assertTrue(Snapshot::get($learner)['course_progress'][0]['certificate_ready']);
    }

    public function test_admin_can_place_fields_on_pdf_and_png_templates_and_generate_preview(): void {
        Storage::fake('local');$course=$this->course();$admin=$this->user('admin');$pdf=app(CertificatePdf::class)->pdf();$pdf->AddPage('L','A4');$pdf->SetFont('helvetica','B',24);$pdf->Text(20,20,'Designed certificate');$bytes=$pdf->Output('template.pdf','S');
        $this->actingAs($admin)->put('/admin/certificates/'.$course.'/template',['template'=>UploadedFile::fake()->createWithContent('design.pdf',$bytes),'placements'=>CertificatePdf::defaults()])->assertRedirect()->assertSessionHasNoErrors();
        $this->get('/admin/certificates/'.$course.'/template')->assertOk()->assertSee('data-placement="full_name"',false);$preview=$this->get('/admin/certificates/'.$course.'/preview.pdf')->assertOk();$this->assertStringStartsWith('%PDF-',$preview->getContent());
        $chunk=fn($type,$data)=>pack('N',strlen($data)).$type.$data.pack('N',crc32($type.$data));$png="\x89PNG\r\n\x1a\n".$chunk('IHDR',pack('NNCCCCC',1,1,8,2,0,0,0)).$chunk('IDAT',gzcompress("\0\xff\xff\xff")).$chunk('IEND','');
        $this->put('/admin/certificates/'.$course.'/template',['template'=>UploadedFile::fake()->createWithContent('design.png',$png),'placements'=>CertificatePdf::defaults()])->assertRedirect()->assertSessionHasNoErrors();$this->get('/admin/certificates/'.$course.'/preview.pdf')->assertOk();
        $this->actingAs($this->user('manager'))->get('/admin/certificates/'.$course.'/template')->assertForbidden();
    }

    public function test_prerequisite_course_and_closed_calls_cannot_be_bypassed(): void {
        $first=$this->course();$next=$this->course();DB::table('courses')->where('id',$next)->update(['prerequisite_course_id'=>$first]);$learner=$this->user();LearningAccess::trial($learner->id,$first);LearningAccess::trial($learner->id,$next);$lesson=$this->lesson($first,1);
        $this->assertFalse(LearningAccess::allowed($learner,$next));Workflow::run($learner,'complete',['lesson_id'=>$lesson]);$this->assertTrue(LearningAccess::allowed($learner,$next));
        $call=$this->createCall($next);DB::table('application_calls')->where('id',$call->id)->update(['closes_at'=>now()->subMinute()]);$this->actingAs($learner)->post('/calls/'.$call->token.'/apply',['motivation'=>'Late'])->assertUnprocessable();$this->get('/admin/calls')->assertForbidden();
        $learner->update(['learning_access_paid'=>true]);$this->postJson('/actions/enrol',['course_id'=>$next])->assertForbidden();
    }

    public function test_trial_api_hides_locked_content_and_rejects_expired_completion_replay(): void {
        $course=$this->course();$learner=$this->user();LearningAccess::trial($learner->id,$course);$first=$this->lesson($course,1);$next=$this->lesson($course,2);$token=$learner->createToken('test')->plainTextToken;$client=(string)\Illuminate\Support\Str::uuid();
        $this->withToken($token)->getJson('/api/snapshot')->assertOk()->assertJsonPath('lessons.0.body','Private body 1')->assertJsonPath('lessons.1.body',null)->assertJsonPath('lessons.1.video_url',null);
        $this->postJson('/api/actions/complete',['lesson_id'=>$next])->assertForbidden();$this->postJson('/api/actions/complete',['lesson_id'=>$first,'client_id'=>$client])->assertOk();
        $this->getJson('/api/snapshot')->assertOk()->assertJsonPath('lessons.1.body','Private body 2');
        $this->travel(12)->hours();$this->getJson('/api/snapshot')->assertForbidden();$this->postJson('/api/actions/complete',['lesson_id'=>$first,'client_id'=>$client])->assertForbidden();
    }

    public function test_opportunity_only_call_approval_records_outcome_without_creating_course_trial(): void {
        $opportunity=Workflow::insert('opportunities',['title'=>'Apprenticeship','type'=>'Apprenticeship','organisation'=>'Lwotowone','location'=>'Kampala','description'=>'Learn at work','deadline'=>now()->addWeek(),'status'=>'published']);$manager=$this->user('manager');
        $this->actingAs($manager)->post('/admin/calls',['title'=>'Apprenticeship call','description'=>'Apply','opportunity_id'=>$opportunity,'opens_at'=>now()->subHour()->toDateTimeString(),'closes_at'=>now()->addDay()->toDateTimeString(),'status'=>'published'])->assertRedirect();$call=DB::table('application_calls')->first();$learner=$this->user();
        $this->actingAs($learner)->post('/calls/'.$call->token.'/apply',['motivation'=>'Work experience'])->assertRedirect();$id=DB::table('call_applications')->value('id');$this->actingAs($manager)->put('/admin/calls/applications/'.$id,['status'=>'approved'])->assertRedirect();
        $this->assertDatabaseHas('applications',['user_id'=>$learner->id,'opportunity_id'=>$opportunity,'status'=>'accepted']);$this->assertDatabaseCount('enrolments',0);
    }
}
