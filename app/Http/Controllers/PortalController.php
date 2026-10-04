<?php
namespace App\Http\Controllers;
use App\Services\{Workflow,Snapshot};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class PortalController extends Controller {
 public function dashboard(Request $r){
  if($r->user()->staff())return view('admin.dashboard',['counts'=>collect(['users','courses','enrolments','submissions','bookings','applications','enterprises'])->mapWithKeys(fn($t)=>[$t=>DB::table($t)->count()])->all()]);
  return view('portal.dashboard',['data'=>Snapshot::get($r->user())]);
 }
 public function section(Request $r,string $section){abort_unless($r->user()->role==='participant',403);abort_unless(in_array($section,['learn','practice','mentorship','opportunities','enterprise','events','notifications','profile']),404);return view('portal.section',['section'=>$section,'data'=>Snapshot::get($r->user())]);}
 public function course(Request $r,string $id){Workflow::enrolled($r->user(),$id);$data=Snapshot::get($r->user());$course=DB::table('courses')->find($id);return view('portal.course',compact('data','course'));}
 public function action(Request $r,string $action){$result=Workflow::run($r->user(),$action,$r->all());return $r->is('api/*')?$result:back()->with('success',$result['message']);}
 public function snapshot(Request $r){abort_unless($r->user()->role==='participant',403);return Snapshot::get($r->user());}
 public function readNotice(Request $r,string $id){$r->user()->notifications()->where('id',$id)->firstOrFail()->markAsRead();return $r->is('api/*')?['ok'=>true]:back();}
 public function profile(Request $r){$d=$r->validate(['name'=>'required|string|max:100','phone'=>'nullable|string|max:40','district'=>'nullable|string|max:100']);$r->user()->update($d);return $r->is('api/*')?['ok'=>true]:back()->with('success','Profile updated.');}
 public function download(Request $r,string $type,string $id){
  abort_unless(in_array($type,['resources','submissions']),404);$item=DB::table($type)->find($id);abort_unless($item,404);
  if($type==='resources'){
   if($r->user()->role==='participant') {abort_unless($item->status==='published',404);Workflow::enrolled($r->user(),$item->course_id);}
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
