<?php
namespace App\Services;
use App\Models\User;
use Illuminate\Support\Facades\DB;
class Snapshot {
 public static function get(User $u): array {
  $out=['user'=>$u->only(['id','name','email','phone','district','role']),'synced_at'=>now()->toIso8601String()];
  foreach(['programs','courses','skills','opportunities','events','posts','announcements'] as $t){$q=DB::table($t)->where('status','published');if($t==='opportunities')$q->whereDate('deadline','>=',today());$out[$t]=$q->orderByDesc('id')->get();}
   $ids=DB::table('enrolments')->where('user_id',$u->id)->pluck('course_id');$access=[];$lessonAccess=[];$lessonOrder=[];$done=DB::table('lesson_progress')->where('user_id',$u->id)->pluck('lesson_id');
   foreach($ids as $courseId){$access[$courseId]=LearningAccess::allowed($u,(int)$courseId);$blocked=false;foreach(LearningAccess::lessons((int)$courseId) as $index=>$lesson){$lessonAccess[$lesson->id]=$access[$courseId]&&!$blocked;$lessonOrder[$lesson->id]=[$index,$lesson->module_title];if(!$done->contains($lesson->id))$blocked=true;}}
   foreach(['lessons','assignments','resources'] as $t)$out[$t]=DB::table($t)->whereIn('course_id',$ids)->where('status','published')->whereIn('course_id',DB::table('courses')->where('status','published')->select('id'))->orderBy('id')->get()->map(function($r)use($t,$access,$lessonAccess,$lessonOrder){
    $r->locked=!($access[$r->course_id]??false);
    if($t==='lessons'){$r->locked=!($lessonAccess[$r->id]??false);$r->sort_order=$lessonOrder[$r->id][0]??PHP_INT_MAX;$r->module_title=$lessonOrder[$r->id][1]??null;$r->media_type=LearningContent::mediaType($r);$r->viewer_url=!$r->locked&&$r->file_path?url('/learning/content/lessons/'.$r->id):null;$r->download_url=$r->viewer_url?url('/api/lessons/'.$r->id.'/download'):null;unset($r->file_path);if($r->locked){$r->body=null;$r->video_url=null;$r->content_body=null;$r->content_url=null;}}
    if($t==='assignments'&&$r->locked)$r->instructions=null;
    if($t==='resources'){$r->locked=$r->locked||($r->lesson_id&&!($lessonAccess[$r->lesson_id]??false));$hasFile=(bool)$r->file_path&&!$r->locked;$r->media_type=LearningContent::mediaType($r);unset($r->file_path);$r->viewer_url=$hasFile?url('/learning/content/resources/'.$r->id):null;$r->download_url=$hasFile?url('/api/resources/'.$r->id.'/download'):null;if($r->locked){$r->content_body=null;$r->content_url=null;}}
    return $r;
   })->filter(fn($r)=>$t!=='lessons'||isset($lessonOrder[$r->id]))->values();
  $out['slots']=DB::table('slots')->where('status','open')->where('starts_at','>',now())->whereIn('mentor_id',User::where('role','mentor')->where('status','active')->select('id'))->whereNotIn('id',DB::table('bookings')->select('slot_id'))->get();
  $out['mentors']=User::where('role','mentor')->where('status','active')->get(['id','name','bio','expertise']);
  foreach(['enrolments','lesson_progress','submissions','practice_logs','bookings','applications','enterprises','transactions','event_registrations'] as $t)$out[$t]=DB::table($t)->where('user_id',$u->id)->orderByDesc('id')->get()->map(function($r)use($t){if($t==='submissions')unset($r->file_path);if($t==='bookings'){$slot=DB::table('slots')->find($r->slot_id);$r->session_title=$slot?->title;$r->starts_at=$slot?->starts_at;$r->ends_at=$slot?->ends_at;$r->location=$slot?->location;$r->mode=$slot?->mode;$r->mentor_name=User::find($slot?->mentor_id)?->name;}return $r;});
  $out['events']=$out['events']->map(function($event){$event->registration_open=now()->lt($event->starts_at);return $event;});
   $out['course_progress']=LearningProgress::fromSnapshot($out);
   $out['course_access']=collect($out['enrolments'])->map(fn($row)=>['course_id'=>$row->course_id,'allowed'=>$access[$row->course_id]??false,'trial_expires_at'=>$row->trial_expires_at,'paid'=>(bool)$row->payment_confirmed_at])->all();
   $out['certificates']=DB::table('course_certificates')->where('user_id',$u->id)->get(['course_id','reference','recommended_at']);
  $out['event_registrations']=$out['event_registrations']->map(function($r){$event=DB::table('events')->find($r->event_id);$r->event_title=$event?->title;$r->can_cancel=$r->status==='registered'&&$event&&now()->lt($event->starts_at);return $r;});
  $out['notifications']=$u->notifications()->latest()->limit(100)->get(['id','data','read_at','created_at']);
  return $out;
 }
}
