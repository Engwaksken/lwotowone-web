<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\Workflow;
use Carbon\CarbonImmutable;
class ReviewController extends Controller {
  public function aiMark(Request $r){
   $data=$r->validate(['submission_ids'=>'required|array|min:1|max:20','submission_ids.*'=>'required|integer|distinct']);
   $items=$this->query($r,'submissions')->whereIn('id',$data['submission_ids'])->where('status','submitted')->get();abort_unless($items->count()===count($data['submission_ids']),422,'Some selected submissions are unavailable or already reviewed.');
   $suggestions=[];$assistant=app(\App\Services\AiAssistant::class);
   foreach($items as $item){$assignment=DB::table('assignments')->find($item->assignment_id);$prompt=json_encode(['assessment_type'=>$assignment->assessment_type??'assignment','assessment'=>$assignment->title,'questions_or_instructions'=>$assignment->instructions,'pass_mark'=>(int)$assignment->pass_mark,'participant_answers_or_work'=>$item->body],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);$answer=$assistant->complete('You assist a human instructor by proposing a provisional score from 0 to 100 and concise feedback. Assess answers against the supplied quiz, exam, or assignment instructions. Return JSON only: {"score": integer, "feedback": string}. Do not make the final decision; never claim approval.',$prompt,500);$decoded=$answer?json_decode($answer,true):null;if(!is_array($decoded)||!isset($decoded['score'])||!is_numeric($decoded['score'])||!isset($decoded['feedback'])||!is_string($decoded['feedback']))continue;$score=max(0,min(100,(int)$decoded['score']));$suggestions[$item->id]=['score'=>$score,'feedback'=>mb_substr($decoded['feedback'],0,5000),'pass_mark'=>(int)$assignment->pass_mark];}
   abort_if(!$suggestions,422,'AI marking is unavailable or did not return usable suggestions.');
   session(['ai_mark_suggestions'=>$suggestions]);return back()->with('success',count($suggestions).' provisional AI mark(s) generated. Review and select suggestions to approve.');
  }
  public function approveAiMarks(Request $r){
   $data=$r->validate(['marks'=>'required|array|min:1|max:20','marks.*'=>'accepted']);
   $suggestions=session('ai_mark_suggestions',[]);$ids=array_map('intval',array_keys($data['marks']));$items=$this->query($r,'submissions')->whereIn('id',$ids)->where('status','submitted')->get()->keyBy('id');abort_unless($items->count()===count($ids),422,'Some selected submissions are no longer available.');
   foreach($data['marks'] as $id=>$approved){$item=$items->get((int)$id);abort_unless(isset($suggestions[$id]),422,'Only current AI suggestions can be approved.');$suggestion=$suggestions[$id];$assignment=DB::table('assignments')->find($item->assignment_id);$status=(int)$suggestion['score'] >= (int)$assignment->pass_mark?'passed':'failed';DB::table('submissions')->where('id',$id)->update(['score'=>$suggestion['score'],'feedback'=>$suggestion['feedback'],'status'=>$status,'reviewer_id'=>$r->user()->id,'updated_at'=>now()]);DB::table('audit_logs')->insert(['user_id'=>$r->user()->id,'action'=>'ai-mark-approved','module'=>'submissions','record_id'=>$id,'created_at'=>now(),'updated_at'=>now()]);unset($suggestions[$id]);}
   session(['ai_mark_suggestions'=>$suggestions]);return back()->with('success',count($ids).' AI mark(s) approved and recorded.');
  }
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
    $rows=$q->orderByDesc('id')->paginate(20)->withQueryString();
    if($type==='submissions')$rows->getCollection()->transform(function($row){$row->attachments=DB::table('learning_attachments')->where('entity_type','submission')->where('entity_id',$row->id)->get();return $row;});
    return view('admin.reviews',['type'=>$type,'rows'=>$rows,'filters'=>$filters,'aiMarkSuggestions'=>session('ai_mark_suggestions',[])]);
  }
 public function update(Request $r,string $type,string $id){
  $item=$this->query($r,$type)->where('id',$id)->first();abort_unless($item,404);
  $rules=match($type){
   'submissions'=>['score'=>'required|integer|min:0|max:100','feedback'=>'required|string|max:5000','status'=>'required|in:passed,returned,failed'],
   'practice_logs'=>['status'=>'required|in:verified,returned','feedback'=>'required|string|max:5000'],
   'bookings'=>['status'=>'required|in:confirmed,completed,cancelled','notes'=>'nullable|string|max:5000'],
   'applications'=>['status'=>'required|in:reviewing,shortlisted,accepted,rejected','feedback'=>'nullable|string|max:5000'],
   'event_registrations'=>['status'=>'required|in:registered,attended,absent,cancelled'],
   'contacts'=>['status'=>'required|in:new,in_progress,resolved'],default=>abort(403)};
  $d=$r->validate($rules);
  if($type==='event_registrations'){\App\Services\EventParticipation::mark((int)$item->event_id,(int)$item->id,$d['status'],$r->user());return back()->with('success','Event attendance updated.');}
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
