<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\Workflow;
class ReviewController extends Controller {
 private function query(Request $r,string $type){
  abort_unless($r->user()->staff(),403);
  abort_unless(in_array($type,['submissions','practice_logs','bookings','applications','event_registrations','contacts','audit_logs']),404);
  $q=DB::table($type);
  if(!$r->user()->manager()){
   if($type==='submissions'&&$r->user()->role==='instructor')$q->whereIn('assignment_id',DB::table('assignments')->whereIn('course_id',DB::table('courses')->where('instructor_id',$r->user()->id)->select('id'))->select('id'));
   elseif($type==='bookings'&&$r->user()->role==='mentor')$q->whereIn('slot_id',DB::table('slots')->where('mentor_id',$r->user()->id)->select('id'));
   else abort(403);
  }return $q;
 }
 public function index(Request $r,string $type){$q=$this->query($r,$type);if($status=$r->query('status'))$q->where('status',$status);return view('admin.reviews',['type'=>$type,'rows'=>$q->orderByDesc('id')->paginate(20)->withQueryString()]);}
 public function update(Request $r,string $type,string $id){
  $item=$this->query($r,$type)->where('id',$id)->first();abort_unless($item,404);
  $rules=match($type){
   'submissions'=>['score'=>'required|integer|min:0|max:100','feedback'=>'required|string|max:5000','status'=>'required|in:passed,returned,failed'],
   'practice_logs'=>['status'=>'required|in:verified,returned','feedback'=>'required|string|max:5000'],
   'bookings'=>['status'=>'required|in:confirmed,completed,cancelled','notes'=>'nullable|string|max:5000'],
   'applications'=>['status'=>'required|in:reviewing,shortlisted,accepted,rejected','feedback'=>'nullable|string|max:5000'],
   'event_registrations'=>['status'=>'required|in:registered,attended,cancelled'],
   'contacts'=>['status'=>'required|in:new,in_progress,resolved'],default=>abort(403)};
  $d=$r->validate($rules);
  if($type==='submissions'){
   $a=DB::table('assignments')->find($item->assignment_id);
   if($d['status']==='passed'&&$d['score']<$a->pass_mark)return back()->withErrors(['score'=>'A passing result must meet the assignment pass mark.']);$d['reviewer_id']=$r->user()->id;
  }
  if($type==='bookings'){
   $allowed=['requested'=>['confirmed','cancelled'],'confirmed'=>['completed','cancelled'],'completed'=>[],'cancelled'=>[]];
   abort_unless(in_array($d['status'],$allowed[$item->status]??[]),422,'Invalid booking transition.');
   $slot=DB::table('slots')->find($item->slot_id);
   if($d['status']==='completed'&&now()->lt($slot->ends_at))return back()->withErrors(['status'=>'Complete a session after its scheduled end.']);
  }
  DB::table($type)->where('id',$id)->update($d+['updated_at'=>now()]);
  DB::table('audit_logs')->insert(['user_id'=>$r->user()->id,'action'=>'review','module'=>$type,'record_id'=>$id,'created_at'=>now(),'updated_at'=>now()]);
  if(isset($item->user_id))Workflow::notify($item->user_id,'Your '.str_replace('_',' ',$type).' was updated','Status: '.$d['status'].'. '.($d['feedback']??$d['notes']??''));
  return back()->with('success','Review saved.');
 }
 public function report(Request $r){abort_unless($r->user()->manager(),403);return response()->streamDownload(function(){
  $f=fopen('php://output','w');fputcsv($f,['Metric','Value']);
  foreach(['users','courses','enrolments','lesson_progress','submissions','practice_logs','bookings','applications','enterprises','event_registrations'] as $t)fputcsv($f,[$t,DB::table($t)->count()]);
  fputcsv($f,['Active participants',DB::table('users')->where('role','participant')->where('status','active')->count()]);
  fputcsv($f,['Completed mentorship sessions',DB::table('bookings')->where('status','completed')->count()]);
  fputcsv($f,['Accepted opportunity applications',DB::table('applications')->where('status','accepted')->count()]);
  fputcsv($f,['Verified skill logs',DB::table('practice_logs')->where('status','verified')->count()]);
  fputcsv($f,['Passed practical work',DB::table('submissions')->where('status','passed')->count()]);
  fputcsv($f,['Recorded income UGX',DB::table('transactions')->where('type','income')->sum('amount')]);
  fputcsv($f,['Recorded expenses UGX',DB::table('transactions')->where('type','expense')->sum('amount')]);fclose($f);
 },'lwotowone-impact.csv',['Content-Type'=>'text/csv']);}
}
