<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\Workflow;
use Carbon\CarbonImmutable;
class ReviewController extends Controller {
 private function query(Request $r,string $type){
  abort_unless($r->user()->staff(),403);
  abort_unless(in_array($type,['submissions','practice_logs','bookings','applications','event_registrations','contacts','audit_logs']),404);
  $q=DB::table($type);
  if(!$r->user()->manager()){
   if($type==='submissions'&&$r->user()->role==='instructor')$q->whereIn('assignment_id',DB::table('assignments')->whereIn('course_id',\App\Services\Catalog::query('courses')->assignedToInstructor((int)$r->user()->id)->select('courses.id'))->select('id'));
   elseif($type==='bookings'&&$r->user()->role==='mentor')$q->whereIn('slot_id',DB::table('slots')->where('mentor_id',$r->user()->id)->select('id'));
   else abort(403);
  }return $q;
 }
  public function index(Request $r,string $type){
   $filters=$r->validate([
    'q'=>'nullable|string|max:255','status'=>'nullable|string|max:40',
    'period'=>'sometimes|required|in:all,week,month,year,custom',
    'start_date'=>'exclude_unless:period,custom|required|date_format:Y-m-d',
    'end_date'=>'exclude_unless:period,custom|required|date_format:Y-m-d|after_or_equal:start_date',
   ]);
   $filters+=['q'=>'','status'=>'','period'=>'all','start_date'=>null,'end_date'=>null];
   $filters['q']=$filters['q']??'';$filters['status']=$filters['status']??'';
   $q=$this->query($r,$type);
   if($filters['status']!==''&&$type!=='audit_logs')$q->where('status',$filters['status']);
   if($filters['q']!==''){
    $fields=match($type){
     'submissions'=>['body','feedback','status'],
     'practice_logs'=>['title','body','status','feedback'],
     'bookings'=>['goal','status','notes'],
     'applications'=>['motivation','status','feedback'],
     'event_registrations'=>['status'],
     'contacts'=>['name','email','message','status'],
     'audit_logs'=>['action','module'],
     default=>[],
    };
    $needle=str_replace(['!','%','_'],['!!','!%','!_'],$filters['q']);$pattern='%'.$needle.'%';
    $q->where(function($query)use($fields,$pattern){foreach($fields as $index=>$field){$method=$index===0?'whereRaw':'orWhereRaw';$query->{$method}("{$field} LIKE ? ESCAPE '!'",[$pattern]);}});
   }
   $today=CarbonImmutable::today(config('app.timezone'));
   [$start,$end]=match($filters['period']){
    'week'=>[$today->startOfWeek(CarbonImmutable::MONDAY),$today->endOfWeek(CarbonImmutable::SUNDAY)],
    'month'=>[$today->startOfMonth(),$today->endOfMonth()],
    'year'=>[$today->startOfYear(),$today->endOfYear()],
    'custom'=>[CarbonImmutable::parse($filters['start_date']),CarbonImmutable::parse($filters['end_date'])],
    default=>[null,null],
   };
   if($start){
    $dateColumn=$type==='practice_logs'?'practised_on':'created_at';
    $from=$dateColumn==='created_at'?$start->startOfDay()->toDateTimeString():$start->toDateString();
    $to=$dateColumn==='created_at'?$end->endOfDay()->toDateTimeString():$end->toDateString();
    $q->whereBetween($dateColumn,[$from,$to]);
   }
   return view('admin.reviews',['type'=>$type,'rows'=>$q->orderByDesc('id')->paginate(20)->withQueryString(),'filters'=>$filters]);
  }
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
