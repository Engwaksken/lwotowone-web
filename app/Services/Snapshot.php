<?php
namespace App\Services;
use App\Models\User;
use Illuminate\Support\Facades\DB;
class Snapshot {
 public static function get(User $u): array {
  $out=['user'=>$u->only(['id','name','email','phone','district','role']),'synced_at'=>now()->toIso8601String()];
  foreach(['programs','courses','skills','opportunities','events','posts','announcements'] as $t){$q=DB::table($t)->where('status','published');if($t==='opportunities')$q->whereDate('deadline','>=',today());$out[$t]=$q->orderByDesc('id')->get();}
  $ids=DB::table('enrolments')->where('user_id',$u->id)->pluck('course_id');
  foreach(['lessons','assignments','resources'] as $t)$out[$t]=DB::table($t)->whereIn('course_id',$ids)->where('status','published')->whereIn('course_id',DB::table('courses')->where('status','published')->select('id'))->orderBy('id')->get()->map(function($r)use($t){if($t==='resources'){unset($r->file_path);$r->download_url=url('/api/resources/'.$r->id.'/download');}return $r;});
  $out['slots']=DB::table('slots')->where('status','open')->where('starts_at','>',now())->whereIn('mentor_id',User::where('role','mentor')->where('status','active')->select('id'))->whereNotIn('id',DB::table('bookings')->select('slot_id'))->get();
  $out['mentors']=User::where('role','mentor')->where('status','active')->get(['id','name','bio','expertise']);
  foreach(['enrolments','lesson_progress','submissions','practice_logs','bookings','applications','enterprises','transactions','event_registrations'] as $t)$out[$t]=DB::table($t)->where('user_id',$u->id)->orderByDesc('id')->get()->map(function($r)use($t){if($t==='submissions')unset($r->file_path);if($t==='bookings'){$slot=DB::table('slots')->find($r->slot_id);$r->session_title=$slot?->title;$r->starts_at=$slot?->starts_at;$r->ends_at=$slot?->ends_at;$r->location=$slot?->location;$r->mode=$slot?->mode;$r->mentor_name=User::find($slot?->mentor_id)?->name;}return $r;});
  $out['notifications']=$u->notifications()->latest()->limit(100)->get(['id','data','read_at','created_at']);
  return $out;
 }
}
