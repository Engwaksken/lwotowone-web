<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\PlatformNotice;
use App\Services\Workflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DownloadRequestTest extends TestCase
{
    use RefreshDatabase;

    private int $course;
    private int $lesson;
    private int $second;
    private User $learner;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Notification::fake();
        $program=Workflow::insert('programs',['title'=>'Program','slug'=>uniqid(),'category'=>'TVET','summary'=>'Text','body'=>'Text','status'=>'published']);
        $this->course=Workflow::insert('courses',['program_id'=>$program,'title'=>'Course','summary'=>'Text','instructor_id'=>$this->user('instructor')->id,'duration_hours'=>1,'status'=>'published']);
        Storage::disk('local')->put('lessons/clip.mp4',str_repeat('v',2048));
        Storage::disk('local')->put('lessons/second.mp4','second');
        $this->lesson=Workflow::insert('lessons',['course_id'=>$this->course,'title'=>'Coop building','body'=>'','content_format'=>'video','content_source'=>'upload','file_path'=>'lessons/clip.mp4','content_language'=>'en','position'=>1,'status'=>'published']);
        $this->second=Workflow::insert('lessons',['course_id'=>$this->course,'title'=>'Second lesson','body'=>'','content_format'=>'video','content_source'=>'upload','file_path'=>'lessons/second.mp4','content_language'=>'en','position'=>2,'status'=>'published']);
        $cohort=DB::table('mel_cohorts')->insertGetId(['name'=>'Cohort','enrollment_category'=>'Youth','learner_number_prefix'=>'LW','learner_number_format'=>'{prefix}-{sequence}','sequence_padding'=>4,'next_sequence'=>1,'active'=>true,'created_at'=>now(),'updated_at'=>now()]);
        $this->learner=$this->user('participant',['profile_complete'=>true,'selected_course_id'=>$this->course]);
        app(\App\Services\Enrollment::class)->confirm($this->learner->id,$cohort,$this->user('manager')->id);
        $this->learner=$this->learner->fresh();
    }

    private function user(string $role, array $extra=[]): User
    {
        return User::create(array_merge(['name'=>ucfirst($role).' '.uniqid(),'email'=>uniqid().'@example.test','password'=>'Long-test-password','role'=>$role,'status'=>'active'],$extra));
    }

    private function stream(array $headers)
    {
        return $this->withHeaders($headers)->get('/learning/content/lessons/'.$this->lesson);
    }

    public function test_learners_can_only_stream_through_the_page_player(): void
    {
        $this->actingAs($this->learner);
        $player=['Sec-Fetch-Dest'=>'video','Sec-Fetch-Site'=>'same-origin','Sec-Fetch-Mode'=>'no-cors','User-Agent'=>'Mozilla/5.0 Chrome/130'];
        $this->stream($player)->assertOk();
        $this->stream(['Sec-Fetch-Dest'=>'iframe','Sec-Fetch-Site'=>'same-origin'])->assertOk();
        // iOS plays video through AppleCoreMedia, which sends no Sec-Fetch headers.
        $this->stream(['User-Agent'=>'AppleCoreMedia/1.0.0.21A329 (iPhone; U; CPU OS 17_0 like Mac OS X)'])->assertOk();

        $this->stream(['Sec-Fetch-Dest'=>'document','Sec-Fetch-Site'=>'none','Sec-Fetch-User'=>'?1'])->assertForbidden();
        $this->stream(['Sec-Fetch-Dest'=>'video','Sec-Fetch-Site'=>'cross-site'])->assertForbidden();
        $this->stream(['User-Agent'=>'Internet Download Manager/6.42'])->assertForbidden();
        $this->stream(['User-Agent'=>'Wget/1.21.4'])->assertForbidden();
        $this->stream(['User-Agent'=>'aria2/1.37.0'])->assertForbidden();

        // Staff reviewing content are not restricted.
        $this->actingAs($this->user('manager'))->stream(['Sec-Fetch-Dest'=>'document','Sec-Fetch-User'=>'?1'])->assertOk();
    }

    public function test_mobile_app_keeps_in_app_playback_with_its_access_token(): void
    {
        $token=$this->learner->createToken('app')->plainTextToken;
        $this->withHeaders(['Authorization'=>'Bearer '.$token,'User-Agent'=>'okhttp/4.12.0'])->get('/api/lessons/'.$this->lesson.'/download')->assertOk();
        $this->app['auth']->forgetGuards();
        $this->withHeaders(['Authorization'=>'','User-Agent'=>'Mozilla/5.0'])->getJson('/api/lessons/'.$this->lesson.'/download')->assertUnauthorized();
    }

    public function test_learner_requests_download_and_manager_approves_limited_downloads(): void
    {
        $this->actingAs($this->learner)->get('/learning/'.$this->course)->assertOk()->assertSee('Request download');
        $this->post('/portal/download-requests',['item_type'=>'lessons','item_id'=>$this->lesson,'message'=>'Weak internet at home'])->assertRedirect()->assertSessionHasNoErrors();
        $this->post('/portal/download-requests',['item_type'=>'lessons','item_id'=>$this->lesson])->assertSessionHasErrors('download');
        $this->get('/learning/'.$this->course)->assertSee('Download requested. Waiting for approval.');
        $request=DB::table('download_requests')->first();
        $this->get('/downloads/'.$request->id)->assertForbidden();

        $manager=$this->user('manager');
        $this->actingAs($manager)->get('/admin/download-requests')->assertOk()->assertSee('Coop building')->assertSee('Weak internet at home')->assertSee('Approve');
        $this->post('/admin/download-requests/'.$request->id.'/approve')->assertRedirect()->assertSessionHasNoErrors();
        Notification::assertSentTo($this->learner,PlatformNotice::class,fn($notice)=>$notice->title==='Download approved');
        $this->assertDatabaseHas('download_requests',['id'=>$request->id,'status'=>'approved','decided_by'=>$manager->id]);

        $this->actingAs($this->learner)->get('/learning/'.$this->course)->assertSee('/downloads/'.$request->id,false)->assertSee('3 of 3 downloads left');
        $this->get('/downloads/'.$request->id)->assertOk()->assertDownload('coop-building.mp4');
        // A download manager resuming or splitting the same download does not use up another download.
        $this->withHeaders(['Range'=>'bytes=1024-'])->get('/downloads/'.$request->id)->assertOk();
        $this->assertSame(1,(int)DB::table('download_requests')->value('downloads_used'));
        $this->withHeaders(['Range'=>''])->get('/downloads/'.$request->id)->assertOk();
        $this->get('/downloads/'.$request->id)->assertOk();
        $this->get('/downloads/'.$request->id)->assertForbidden();
        $this->get('/learning/'.$this->course)->assertSee('Your download access has ended.')->assertSee('Request download again');

        // Another learner cannot use this approval.
        $this->actingAs($this->user('participant',['profile_complete'=>true]))->get('/downloads/'.$request->id)->assertNotFound();
    }

    public function test_approval_expires_after_seven_days_and_can_be_withdrawn(): void
    {
        $this->actingAs($this->learner)->post('/portal/download-requests',['item_type'=>'lessons','item_id'=>$this->lesson]);
        $request=DB::table('download_requests')->first();
        $this->actingAs($this->user('manager'))->post('/admin/download-requests/'.$request->id.'/approve');

        $this->travel(8)->days();
        $this->actingAs($this->learner)->get('/downloads/'.$request->id)->assertForbidden();
        $this->travelBack();

        $this->actingAs($this->user('manager'))->post('/admin/download-requests/'.$request->id.'/revoke',['decision_note'=>'Shared outside the course'])->assertSessionHasNoErrors();
        $this->actingAs($this->learner)->get('/downloads/'.$request->id)->assertForbidden();
        $this->get('/learning/'.$this->course)->assertSee('Your download request was declined.')->assertSee('Shared outside the course');
    }

    public function test_rejected_learner_sees_reason_and_can_ask_again(): void
    {
        $this->actingAs($this->learner)->post('/portal/download-requests',['item_type'=>'lessons','item_id'=>$this->lesson]);
        $request=DB::table('download_requests')->first();
        $this->actingAs($this->user('manager'))->post('/admin/download-requests/'.$request->id.'/reject',['decision_note'=>'Watch it online'])->assertSessionHasNoErrors();
        $this->post('/admin/download-requests/'.$request->id.'/approve')->assertSessionHasErrors('download');

        $this->actingAs($this->learner)->get('/learning/'.$this->course)->assertSee('Watch it online')->assertSee('Request download again');
        $this->post('/portal/download-requests',['item_type'=>'lessons','item_id'=>$this->lesson])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('download_requests',['id'=>$request->id,'status'=>'pending','decision_note'=>null]);
    }

    public function test_lesson_attachments_need_an_approved_download(): void
    {
        $attachment=DB::table('learning_attachments')->insertGetId(['entity_type'=>'lesson','entity_id'=>$this->lesson,'file_path'=>'attachments/notes.pdf','original_name'=>'notes.pdf','created_at'=>now(),'updated_at'=>now()]);
        Storage::disk('local')->put('attachments/notes.pdf','PDF');
        $this->actingAs($this->learner)->get('/files/attachments/'.$attachment)->assertForbidden();
        $this->get('/learning/'.$this->course)->assertSee('notes.pdf')->assertSee('available after your download request is approved');

        $this->post('/portal/download-requests',['item_type'=>'lessons','item_id'=>$this->lesson]);
        $request=DB::table('download_requests')->first();
        $this->actingAs($this->user('manager'))->post('/admin/download-requests/'.$request->id.'/approve');
        $this->actingAs($this->learner)->get('/downloads/'.$request->id.'/attachments/'.$attachment)->assertOk()->assertDownload('notes.pdf');
        $this->get('/downloads/'.$request->id.'/attachments/999')->assertNotFound();
    }

    public function test_learners_cannot_request_locked_or_text_only_content_and_only_managers_review(): void
    {
        $this->actingAs($this->learner)->post('/portal/download-requests',['item_type'=>'lessons','item_id'=>$this->second])->assertSessionHasErrors('download');
        $text=Workflow::insert('lessons',['course_id'=>$this->course,'title'=>'Reading','body'=>'Text only','content_format'=>'text','content_source'=>'write','content_body'=>'Text only','content_language'=>'en','position'=>0,'status'=>'published']);
        $this->post('/portal/download-requests',['item_type'=>'lessons','item_id'=>$text])->assertSessionHasErrors('download');
        $this->post('/portal/download-requests',['item_type'=>'users','item_id'=>1])->assertSessionHasErrors('item_type');
        $this->assertDatabaseCount('download_requests',0);

        $this->get('/admin/download-requests')->assertForbidden();
        $this->actingAs($this->user('instructor'))->get('/admin/download-requests')->assertForbidden();
    }
}
