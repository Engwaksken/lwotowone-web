<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Services\{Workflow,Snapshot};
class LearningEventsTest extends TestCase {
 use RefreshDatabase;
 private function learner(){return User::create(['name'=>'Learner','email'=>uniqid().'@example.test','password'=>'Test-password-long','role'=>'participant','status'=>'active']);}
 private function event(){return Workflow::insert('events',['title'=>'Youth skills day','description'=>'Hands-on learning','starts_at'=>now()->addDay(),'ends_at'=>now()->addDay()->addHours(2),'location'=>'Kampala','capacity'=>1,'status'=>'published']);}
 private function course(){
  $teacher=$this->learner();$teacher->update(['role'=>'instructor']);
  $program=Workflow::insert('programs',['title'=>'Skills','slug'=>uniqid(),'category'=>'TVET','summary'=>'Skills','body'=>'Skills','status'=>'published']);
  return Workflow::insert('courses',['title'=>'Poultry skills','program_id'=>$program,'instructor_id'=>$teacher->id,'summary'=>'Practical training','duration_hours'=>4,'status'=>'published']);
 }
 public function test_cancellation_retains_history_frees_capacity_and_enforces_ownership():void {
  $e=$this->event();$owner=$this->learner();$other=$this->learner();
  $id=Workflow::run($owner,'register-event',['event_id'=>$e])['id'];
  $this->actingAs($other)->postJson('/api/actions/cancel-event',['registration_id'=>$id])->assertForbidden();
  $this->actingAs($owner)->postJson('/api/actions/cancel-event',['registration_id'=>$id,'client_id'=>'ecba79ad-2a93-4c84-9e63-0993b92d072f'])->assertOk();
  $this->postJson('/api/actions/cancel-event',['registration_id'=>$id,'client_id'=>'ecba79ad-2a93-4c84-9e63-0993b92d072f'])->assertOk();
  $this->assertDatabaseHas('event_registrations',['id'=>$id,'status'=>'cancelled']);
  Workflow::run($other,'register-event',['event_id'=>$e]);
  $this->postJson('/actions/register-event',['event_id'=>$e])->assertUnprocessable();
  $this->assertDatabaseCount('event_registrations',2);
  $this->assertDatabaseCount('sync_actions',1);
  $this->get('/portal/events')->assertOk()->assertSee('Cancelled')->assertSee('Register again');
 }
 public function test_re_registration_reuses_record_and_preserves_created_date():void {
  $u=$this->learner();$e=$this->event();$id=Workflow::run($u,'register-event',['event_id'=>$e])['id'];
  $created=DB::table('event_registrations')->find($id)->created_at;
  Workflow::run($u,'cancel-event',['registration_id'=>$id]);
  $this->assertFalse(Snapshot::get($u)['event_registrations']->first()->can_cancel);
  $result=Workflow::run($u,'register-event',['event_id'=>$e]);
  $this->assertSame($id,$result['id']);$this->assertDatabaseCount('event_registrations',1);
  $this->assertDatabaseHas('event_registrations',['id'=>$id,'status'=>'registered','created_at'=>$created]);
  $this->assertTrue(Snapshot::get($u)['event_registrations']->first()->can_cancel);
  $this->assertSame('Youth skills day',Snapshot::get($u)['event_registrations']->first()->event_title);
 }
 public function test_started_or_attended_events_cannot_be_cancelled():void {
  $u=$this->learner();$e=$this->event();$id=Workflow::run($u,'register-event',['event_id'=>$e])['id'];
  DB::table('event_registrations')->where('id',$id)->update(['status'=>'attended']);
  $this->actingAs($u)->postJson('/api/actions/cancel-event',['registration_id'=>$id])->assertUnprocessable();
  DB::table('event_registrations')->where('id',$id)->update(['status'=>'registered']);
  DB::table('events')->where('id',$e)->update(['starts_at'=>now()]);
  $this->postJson('/api/actions/cancel-event',['registration_id'=>$id])->assertUnprocessable();
  $this->assertFalse(Snapshot::get($u)['event_registrations']->first()->can_cancel);
  $this->assertFalse(Snapshot::get($u)['events']->first()->registration_open);
  $this->assertDatabaseHas('event_registrations',['id'=>$id,'status'=>'registered']);
 }
 public function test_progress_uses_only_published_enrolled_content_and_requires_assessments():void {
  $u=$this->learner();$c=$this->course();$unrelated=$this->course();
  Workflow::run($u,'enrol',['course_id'=>$c]);
  $first=Workflow::insert('lessons',['course_id'=>$c,'title'=>'First lesson','body'=>'Read','position'=>1,'status'=>'published']);
  $next=Workflow::insert('lessons',['course_id'=>$c,'title'=>'Next lesson','body'=>'Read','position'=>2,'status'=>'published']);
  Workflow::insert('lessons',['course_id'=>$c,'title'=>'Draft','body'=>'Draft','position'=>3,'status'=>'draft']);
  $a=Workflow::insert('assignments',['course_id'=>$c,'title'=>'Practical','instructions'=>'Practise','pass_mark'=>60,'status'=>'published']);
  Workflow::insert('assignments',['course_id'=>$c,'title'=>'Draft practical','instructions'=>'Draft','pass_mark'=>60,'status'=>'draft']);
  Workflow::run($u,'complete',['lesson_id'=>$first]);
  $snapshot=Snapshot::get($u);$this->assertCount(1,$snapshot['course_progress']);$p=$snapshot['course_progress'][0];
  $this->assertSame(50,$p['lesson_percent']);$this->assertSame($next,$p['next_lesson_id']);$this->assertSame(1,$p['assignments_total']);$this->assertFalse($p['certificate_ready']);
  Workflow::run($u,'complete',['lesson_id'=>$next]);
  $this->assertFalse(Snapshot::get($u)['course_progress'][0]['certificate_ready']);
  Workflow::insert('submissions',['user_id'=>$u->id,'assignment_id'=>$a,'body'=>'Evidence','status'=>'passed','score'=>70]);
  $p=Snapshot::get($u)['course_progress'][0];$this->assertSame(100,$p['lesson_percent']);$this->assertTrue($p['certificate_ready']);$this->assertNull($p['next_lesson_id']);
  $this->actingAs($u)->get('/learning/'.$c)->assertOk()->assertSee('Learning progress')->assertSee('certificate is ready');
  $this->get('/certificates/'.$c)->assertOk();$this->get('/dashboard')->assertOk()->assertSee('My learning progress');
  $token=$u->createToken('test')->plainTextToken;
  $this->withToken($token)->getJson('/api/snapshot')->assertOk()->assertJsonPath('course_progress.0.certificate_ready',true);
 }
 public function test_empty_course_never_claims_a_certificate():void {
  $u=$this->learner();$c=$this->course();Workflow::run($u,'enrol',['course_id'=>$c]);
  $p=Snapshot::get($u)['course_progress'][0];$this->assertSame(0,$p['lesson_percent']);$this->assertFalse($p['certificate_ready']);$this->assertNull($p['next_lesson_id']);
 }
}
