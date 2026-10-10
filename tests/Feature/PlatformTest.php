<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB,Hash};
use App\Models\User;
use App\Services\Workflow;
class PlatformTest extends TestCase {
 use RefreshDatabase;
 private function user($role='participant'){return User::create(['name'=>'Test '.$role,'email'=>uniqid().'@example.test','password'=>Hash::make('A-long-test-password'),'role'=>$role,'status'=>'active','profile_complete'=>$role==='participant','learning_access_paid'=>$role==='participant']);}
 private function course($instructor){$p=Workflow::insert('programs',['title'=>'Test programme','slug'=>uniqid(),'category'=>'TVET','summary'=>'Test','body'=>'Test','status'=>'published']);$id=Workflow::insert('courses',['title'=>'Test course','program_id'=>$p,'instructor_id'=>$instructor->id,'summary'=>'Test','level'=>'Beginner','duration_hours'=>2,'status'=>'published']);Workflow::insert('course_instructors',['course_id'=>$id,'user_id'=>$instructor->id]);return $id;}
 public function test_registration_cannot_escalate_role():void{$this->post('/api/register',['name'=>'Learner','email'=>'learner@example.test','password'=>'A-long-test-password','password_confirmation'=>'A-long-test-password','consent'=>1,'role'=>'admin'])->assertCreated();$this->assertDatabaseHas('users',['email'=>'learner@example.test','role'=>'participant']);}
  public function test_inactive_account_cannot_login():void{$u=$this->user();$u->update(['status'=>'inactive']);$this->postJson('/api/login',['email'=>$u->email,'password'=>'A-long-test-password'])->assertUnprocessable();}
  public function test_participants_and_staff_can_manage_their_own_profile_details():void{
   $staff=$this->user('manager');
   $this->actingAs($staff)->get('/profile')->assertOk()->assertSee($staff->email)->assertSee('About me');
   $this->post('/profile',['name'=>'Programme Lead','phone'=>'+256700000001','district'=>'Kampala','expertise'=>'Youth development','bio'=>'I support practical learning.'])->assertRedirect();
   $this->assertDatabaseHas('users',['id'=>$staff->id,'name'=>'Programme Lead','district'=>'Kampala','expertise'=>'Youth development','bio'=>'I support practical learning.']);
   $participant=$this->user();
   $this->actingAs($participant)->get('/profile')->assertOk();
   $this->get('/portal/profile')->assertOk();
  }
 public function test_participant_cannot_manage_cms():void{$this->actingAs($this->user())->get('/admin/pages')->assertForbidden();}
 public function test_instructor_cannot_edit_another_course():void{$a=$this->user('instructor');$id=$this->course($a);$this->actingAs($this->user('instructor'))->get('/admin/courses/'.$id.'/edit')->assertNotFound();}
 public function test_progress_requires_enrolment_and_is_idempotent():void{$u=$this->user();$c=$this->course($this->user('instructor'));$l=Workflow::insert('lessons',['course_id'=>$c,'title'=>'Lesson','body'=>'Text','position'=>1,'status'=>'published']);$this->actingAs($u)->postJson('/actions/complete',['lesson_id'=>$l])->assertForbidden();Workflow::insert('enrolments',['user_id'=>$u->id,'course_id'=>$c,'payment_confirmed_at'=>now()]);Workflow::run($u,'complete',['lesson_id'=>$l]);Workflow::run($u,'complete',['lesson_id'=>$l]);$this->assertDatabaseCount('lesson_progress',1);}
 public function test_mentorship_slot_cannot_be_double_booked():void{$m=$this->user('mentor');$s=Workflow::insert('slots',['mentor_id'=>$m->id,'title'=>'Session','starts_at'=>now()->addDay(),'ends_at'=>now()->addDay()->addHour(),'mode'=>'Online','location'=>'Meeting link to follow','status'=>'open']);Workflow::run($this->user(),'book',['slot_id'=>$s,'goal'=>'Advice']);$this->actingAs($this->user())->postJson('/actions/book',['slot_id'=>$s,'goal'=>'Advice'])->assertUnprocessable();$this->assertDatabaseCount('bookings',1);}
 public function test_income_requires_owned_enterprise_and_replay_does_not_duplicate():void{$u=$this->user();$e=Workflow::run($u,'enterprise',['title'=>'Farm','sector'=>'Agriculture','idea'=>'Poultry','stage'=>'idea'])['id'];$p=['enterprise_id'=>$e,'type'=>'income','amount'=>5000,'description'=>'Sale','occurred_on'=>today()->toDateString(),'client_id'=>'e57429a6-96dc-4ae9-8625-b0c53bf82341'];Workflow::run($u,'income',$p);Workflow::run($u,'income',$p);$this->assertDatabaseCount('transactions',1);$this->actingAs($this->user())->postJson('/actions/income',$p)->assertForbidden();}
 public function test_event_capacity_enforced():void{$e=Workflow::insert('events',['title'=>'Skills day','description'=>'Test','starts_at'=>now()->addDay(),'ends_at'=>now()->addDays(2),'location'=>'Uganda','capacity'=>1,'status'=>'published']);Workflow::run($this->user(),'register-event',['event_id'=>$e]);$this->actingAs($this->user())->postJson('/actions/register-event',['event_id'=>$e])->assertUnprocessable();}
 public function test_certificate_requires_passing_practical_work():void{$u=$this->user();$teacher=$this->user('instructor');$c=$this->course($teacher);$l=Workflow::insert('lessons',['course_id'=>$c,'title'=>'Lesson','body'=>'Text','position'=>1,'status'=>'published']);$a=Workflow::insert('assignments',['course_id'=>$c,'title'=>'Practical','instructions'=>'Test','pass_mark'=>60,'status'=>'published']);Workflow::insert('enrolments',['user_id'=>$u->id,'course_id'=>$c,'payment_confirmed_at'=>now()]);Workflow::run($u,'complete',['lesson_id'=>$l]);$this->actingAs($u)->get('/certificates/'.$c)->assertUnprocessable();Workflow::insert('submissions',['user_id'=>$u->id,'assignment_id'=>$a,'body'=>'Evidence','status'=>'passed','score'=>70]);$this->get('/certificates/'.$c)->assertForbidden();$this->actingAs($teacher)->post('/admin/certificates/'.$c.'/recommend',['user_id'=>$u->id])->assertRedirect();$this->actingAs($u)->get('/certificates/'.$c)->assertOk();}
 public function test_draft_lessons_not_in_snapshot():void{$u=$this->user();$c=$this->course($this->user('instructor'));Workflow::insert('enrolments',['user_id'=>$u->id,'course_id'=>$c,'payment_confirmed_at'=>now()]);Workflow::insert('lessons',['course_id'=>$c,'title'=>'Draft','body'=>'Private','position'=>1,'status'=>'draft']);$token=$u->createToken('test')->plainTextToken;$this->withToken($token)->getJson('/api/snapshot')->assertOk()->assertJsonCount(0,'lessons');}
 public function test_public_cms_and_participant_pages_render():void {
   foreach(['/','/login','/register','/forgot-password','/contact','/explore/courses','/explore/programs','/explore/posts','/explore/events','/explore/opportunities'] as $url)$this->get($url)->assertOk();
   $this->get('/login')->assertSee('Back to website');$this->get('/register')->assertSee('Back to website');
  $this->actingAs($this->user('admin'));
  foreach(array_keys(config('modules')) as $module){$this->get('/admin/'.$module)->assertOk();$this->get('/admin/'.$module.'/create')->assertOk();}
  foreach(['submissions','practice_logs','bookings','applications','event_registrations','contacts','audit_logs'] as $type)$this->get('/admin/reviews/'.$type)->assertOk();
   $this->actingAs($this->user());$this->get('/dashboard')->assertOk()->assertSee('Dashboard')->assertDontSee('About us');
  foreach(['learn','practice','mentorship','opportunities','enterprise','events','notifications','profile'] as $section)$this->get('/portal/'.$section)->assertOk();
 }
 public function test_private_resources_require_enrolment_and_never_expose_storage_paths():void {
  $teacher=$this->user('instructor');$c=$this->course($teacher);$u=$this->user();
  \Illuminate\Support\Facades\Storage::fake('local');\Illuminate\Support\Facades\Storage::disk('local')->put('resources/guide.txt','A learning guide');
  $id=Workflow::insert('resources',['course_id'=>$c,'title'=>'Guide','description'=>'Read this','file_path'=>'resources/guide.txt','status'=>'published']);
  $this->actingAs($u)->get('/files/resources/'.$id)->assertForbidden();
  Workflow::insert('enrolments',['user_id'=>$u->id,'course_id'=>$c,'payment_confirmed_at'=>now()]);$this->get('/files/resources/'.$id)->assertOk();
  $snapshot=\App\Services\Snapshot::get($u);$this->assertFalse(property_exists($snapshot['resources']->first(),'file_path'));
  $this->actingAs($this->user('instructor'))->get('/files/resources/'.$id)->assertForbidden();
 }
 public function test_cancelled_event_registration_frees_capacity():void {
  $e=Workflow::insert('events',['title'=>'Skills day','description'=>'Test','starts_at'=>now()->addDay(),'ends_at'=>now()->addDays(2),'location'=>'Uganda','capacity'=>1,'status'=>'published']);
  Workflow::run($this->user(),'register-event',['event_id'=>$e]);DB::table('event_registrations')->update(['status'=>'cancelled']);
  Workflow::run($this->user(),'register-event',['event_id'=>$e]);$this->assertDatabaseCount('event_registrations',2);
 }
}
