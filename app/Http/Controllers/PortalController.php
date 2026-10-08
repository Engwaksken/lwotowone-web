<?php
namespace App\Http\Controllers;
use App\Services\{Workflow,Snapshot};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class PortalController extends Controller {
 public function pushNotice(Request $r){
  $r->validate(['title'=>'required|string','body'=>'required|string']);
  $count = (new \App\Services\NotificationService())->sendToParticipants($r->title,$r->body,$r->all());
  return ['ok'=>true,'sent'=>$count,'message'=>'Push notification sent to '.$count.' participants.'];
}

public function sendSms(Request $r){
  $r->validate(['body'=>'required|string']);
  $count = (new \App\Services\NotificationService())->sendSmsToParticipants('Notification',$r->body);
  return ['ok'=>true,'sent'=>$count,'message'=>'SMS sent to '.$count.' participants.'];
}

public function dashboard(Request $r){
  if($r->user()->role==='participant'&&!$r->user()->profile_complete)return redirect('/profile')->with('success','Complete your learner profile to continue.');
  if($r->user()->staff())return view('admin.dashboard',['counts'=>collect(['users','courses','enrolments','submissions','bookings','applications','enterprises'])->mapWithKeys(fn($t)=>[$t=>DB::table($t)->count()])->all()]);
  return view('portal.dashboard',['data'=>Snapshot::get($r->user())]);
 }
 public function section(Request $r,string $section){
  abort_unless(in_array($section,['learn','practice','mentorship','opportunities','enterprise','events','notifications','profile','payment']),404);
  abort_unless($r->user()->role==='participant'||in_array($section,['profile','payment']),403);
  if($section==='profile')return view('portal.profile',['user'=>$r->user()]);
  if($section==='payment')return view('portal.payment',['user'=>$r->user(),'gateways'=>DB::table('payment_gateways')->where('active',true)->orderBy('name')->get()]);
  abort_unless($r->user()->profile_complete,403,'Complete your learner profile first.');
  if($section==='learn'&&!$r->user()->learning_access_paid)return redirect('/portal/payment');
  return view('portal.section',['section'=>$section,'data'=>Snapshot::get($r->user())]);
 }
 public function course(Request $r,string $id){abort_unless($r->user()->profile_complete,403,'Complete your learner profile first.');abort_unless($r->user()->learning_access_paid,403,'Payment is required to access learning materials.');Workflow::enrolled($r->user(),$id);$data=Snapshot::get($r->user());$course=DB::table('courses')->find($id);return view('portal.course',compact('data','course'));}
 public function action(Request $r,string $action){$result=Workflow::run($r->user(),$action,$r->all());return $r->is('api/*')?$result:back()->with('success',$result['message']);}
 public function snapshot(Request $r){abort_unless($r->user()->role==='participant',403);return Snapshot::get($r->user());}
 public function readNotice(Request $r,string $id){$r->user()->notifications()->where('id',$id)->firstOrFail()->markAsRead();return $r->is('api/*')?['ok'=>true]:back();}
 public function profile(Request $r){
  $d=$r->validate(['name'=>'required|string|max:100','phone'=>'required|string|max:40','venture'=>'nullable|string|max:255','enrollment_date'=>'nullable|date','learner_no'=>'nullable|string|max:64','enrollment_category'=>'required|string|max:100','gender'=>'required|in:Female,Male,Other,Prefer not to say','location'=>'required|string|max:255','urban_rural'=>'required|in:Urban,Rural','learner_age'=>'required|integer|min:10|max:100','refugee'=>'required|boolean','settlement'=>'nullable|string|max:255','pwd'=>'required|boolean','impairment'=>'nullable|string|max:255','education_level'=>'required|string|max:100','learner_status'=>'required|string|max:100','verified_outcomes'=>'nullable|string|max:5000','other_verified_outcome'=>'nullable|string|max:5000','yiw_before'=>'nullable|string|max:100','transformation_objective'=>'nullable|string|max:5000','after_work_status'=>'nullable|string|max:100','after_work_pathway'=>'nullable|string|max:255']);
  $d['profile_complete']=true;$r->user()->update($d);return $r->is('api/*')?['ok'=>true]:back()->with('success','Learner profile saved.');
 }
 public function download(Request $r,string $type,string $id){
  abort_unless(in_array($type,['resources','submissions']),404);$item=DB::table($type)->find($id);abort_unless($item,404);
  if($type==='resources'){
   if($r->user()->role==='participant') {abort_unless($r->user()->profile_complete&&$r->user()->learning_access_paid,403,'Complete your profile and confirm payment before accessing learning resources.');abort_unless($item->status==='published',404);Workflow::enrolled($r->user(),$item->course_id);}
   else abort_unless($r->user()->manager()||DB::table('courses')->where('id',$item->course_id)->where('instructor_id',$r->user()->id)->exists(),403);
  }else{
   $a=DB::table('assignments')->find($item->assignment_id);
   abort_unless($r->user()->id===$item->user_id||$r->user()->manager()||DB::table('courses')->where('id',$a->course_id)->where('instructor_id',$r->user()->id)->exists(),403);
  }
  abort_unless($item->file_path,404);return \Illuminate\Support\Facades\Storage::disk('local')->download($item->file_path);
 }
 public function certificate(Request $r,string $id){
  Workflow::enrolled($r->user(),$id);$c=DB::table('courses')->find($id);
  $lessons=DB::table('lessons')->where('course_id',$id)->where('status','published')->pluck('id');
  abort_unless($lessons->count()>0&&DB::table('lesson_progress')->where('user_id',$r->user()->id)->whereIn('lesson_id',$lessons)->count()===$lessons->count(),422,'Complete all published lessons first.');
  $assignments=DB::table('assignments')->where('course_id',$id)->where('status','published')->pluck('id');
  abort_unless(DB::table('submissions')->where('user_id',$r->user()->id)->whereIn('assignment_id',$assignments)->where('status','passed')->count()===$assignments->count(),422,'Pass all published practical assignments first.');
  return view('portal.certificate',['course'=>$c,'user'=>$r->user()]);
 }
}
