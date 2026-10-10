<?php
namespace App\Http\Controllers;
use App\Services\{Workflow,Snapshot};
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class PortalController extends Controller {
  public function pushNotice(Request $r){
    abort_unless($r->user()->manager(),403);
    $d=$r->validate(['title'=>'required|string|max:255','body'=>'required|string|max:5000','data'=>'sometimes|array']);
    $count = app(\App\Services\NotificationService::class)->sendToParticipants($d['title'],$d['body'],$d['data']??[]);
   return ['ok'=>true,'sent'=>$count,'message'=>'Push notification sent to '.$count.' participants.'];
 }

  public function sendSms(Request $r){
    abort_unless($r->user()->manager(),403);
    $d=$r->validate(['body'=>'required|string|max:1600']);
    $count = app(\App\Services\NotificationService::class)->sendSmsToParticipants('Notification',$d['body']);
   return ['ok'=>true,'sent'=>$count,'message'=>'SMS sent to '.$count.' participants.'];
 }

  public function dashboard(Request $r){
   if($r->user()->role==='participant'&&!$r->user()->profile_complete)return redirect('/profile')->with('success','Complete your learner profile to continue.');
    if($r->user()->staff())return view('admin.dashboard',['metrics'=>app(\App\Services\DashboardMetrics::class)->forUser($r->user())]);
   return view('portal.dashboard',['data'=>Snapshot::get($r->user())]);
 }
  public function section(Request $r,string $section){
     abort_unless(in_array($section,['learn','practice','mentorship','opportunities','enterprise','events','notifications','profile','payment']),404);
     abort_unless($r->user()->role==='participant'||$section==='profile',403);
     if($section==='profile')return view('portal.profile',['user'=>$r->user(),'courses'=>DB::table('courses')->where('status','published')->orderBy('title')->get(['id','title']),'settlements'=>DB::table('mel_settlements')->where('active',true)->orderBy('name')->get(['id','name'])]);
    if($section==='payment')return view('portal.payment',['user'=>$r->user(),'gateways'=>DB::table('payment_gateways')->where('active',true)->orderBy('name')->get()]);
    abort_unless($r->user()->profile_complete,403,'Complete your learner profile first.');
     if($section==='learn'&&!$r->user()->learningAccessOpen()&&!DB::table('enrolments')->where('user_id',$r->user()->id)->exists())return redirect('/portal/payment');
   $earnings=$section==='enterprise'?\App\Services\Earnings::get($r->user(),$r->query()):null;
    $practiceLogs=null;
    $practiceFilters=null;
    if($section==='practice'){
      $practiceFilters=$r->validate([
        'q'=>'nullable|string|max:255',
        'period'=>'sometimes|required|in:all,week,month,year,custom',
        'start_date'=>'exclude_unless:period,custom|required|date_format:Y-m-d',
        'end_date'=>'exclude_unless:period,custom|required|date_format:Y-m-d|after_or_equal:start_date',
      ]);
      $practiceFilters+=['q'=>'','period'=>'all','start_date'=>null,'end_date'=>null];
      $practiceFilters['q']=$practiceFilters['q']??'';
      $today=CarbonImmutable::today(config('app.timezone'));
      [$start,$end]=match($practiceFilters['period']){
        'week'=>[$today->startOfWeek(CarbonImmutable::MONDAY),$today->endOfWeek(CarbonImmutable::SUNDAY)],
        'month'=>[$today->startOfMonth(),$today->endOfMonth()],
        'year'=>[$today->startOfYear(),$today->endOfYear()],
        'custom'=>[CarbonImmutable::parse($practiceFilters['start_date']),CarbonImmutable::parse($practiceFilters['end_date'])],
        default=>[null,null],
      };
      $query=DB::table('practice_logs')->join('skills','skills.id','=','practice_logs.skill_id')
        ->where('practice_logs.user_id',$r->user()->id)
        ->select('practice_logs.*','skills.title as skill_title');
      if($start)$query->whereBetween('practice_logs.practised_on',[$start->toDateString(),$end->toDateString()]);
      if($practiceFilters['q']!==''){
        $needle=str_replace(['!','%','_'],['!!','!%','!_'],$practiceFilters['q']);
        $pattern='%'.$needle.'%';
        $query->where(function($q)use($pattern){
          $q->whereRaw("practice_logs.title LIKE ? ESCAPE '!'",[$pattern])
            ->orWhereRaw("practice_logs.body LIKE ? ESCAPE '!'",[$pattern])
            ->orWhereRaw("skills.title LIKE ? ESCAPE '!'",[$pattern]);
        });
      }
      $practiceLogs=$query->orderByDesc('practice_logs.practised_on')->orderByDesc('practice_logs.id')->paginate(10)->withQueryString();
    }
    $snapshot=Snapshot::get($r->user());
    if($section==='mentorship')$snapshot['mentors']=app(\App\Services\MentorMatcher::class)->rank($r->user(),collect($snapshot['mentors']));
    return view('portal.section',['section'=>$section,'data'=>$snapshot,'earnings'=>$earnings,
      'practiceLogs'=>$practiceLogs,'practiceFilters'=>$practiceFilters]);
  }
  public function course(Request $r,string $id){abort_unless($r->user()->profile_complete,403,'Complete your learner profile first.');Workflow::enrolled($r->user(),$id);$enrollment=\App\Services\LearningAccess::enrollment($r->user(),(int)$id);abort_unless($enrollment->trial_started_at||\App\Services\LearningAccess::allowed($r->user(),(int)$id),403,'Payment confirmation is required.');$data=Snapshot::get($r->user());$course=DB::table('courses')->find($id);return view('portal.course',compact('data','course'));}
 public function action(Request $r,string $action){$result=Workflow::run($r->user(),$action,$r->all());return $r->is('api/*')?$result:back()->with('success',$result['message']);}
  public function snapshot(Request $r){abort_unless($r->user()->role==='participant',403);abort_unless($r->user()->profile_complete&&($r->user()->learning_access_paid||DB::table('enrolments')->where('user_id',$r->user()->id)->where('trial_expires_at','>',now())->exists()),403,'Complete your profile and confirm payment or obtain an approved trial to access learning data.');return Snapshot::get($r->user());}
  public function registerDeviceToken(Request $r){
    abort_unless($r->user()->role==='participant',403);
    $data=$r->validate(['token'=>'required|string|max:4096']);
    $r->user()->forceFill(['fcm_token'=>$data['token']])->save();
    return response()->json(['ok'=>true]);
  }
  public function removeDeviceToken(Request $r){
    abort_unless($r->user()->role==='participant',403);
    $r->user()->forceFill(['fcm_token'=>null])->save();
    return response()->json(['ok'=>true]);
  }
 public function createPaymentIntent(Request $r){
   abort_unless($r->user()->role==='participant',403);
   $d=$r->validate([
     'amount'=>'required|numeric|min:0.01|max:99999999.99',
     'currency'=>'required|in:UGX,USD,EUR,GBP,KES',
     'enterprise_id'=>'required|integer|min:1',
     'type'=>'required|in:income,expense',
     'description'=>'nullable|string|max:255',
   ]);
   abort_unless(DB::table('enterprises')->where('id',$d['enterprise_id'])->where('user_id',$r->user()->id)->exists(),403);
   $result = app(\App\Services\PaymentService::class)->createPaymentIntent(
     (float)$d['amount'],strtolower($d['currency']),$d['enterprise_id'],$d['type'],$d['description']??''
   );
   if($result['success'])return response()->json(['ok'=>true,'client_secret'=>$result['client_secret'],'id'=>$result['id']],200);
   return response()->json(['ok'=>false,'error'=>$result['error']],$result['error']==='missing_config'?503:502);
 }
 public function listTransactions(Request $r){
   abort_unless($r->user()->role==='participant',403);
   $d=$r->validate(['enterprise_id'=>'sometimes|integer|min:1','type'=>'sometimes|in:income,expense']);
   $enterpriseId=$d['enterprise_id']??null;
   if($enterpriseId)abort_unless(DB::table('enterprises')->where('id',$enterpriseId)->where('user_id',$r->user()->id)->exists(),403);
   $transactions = app(\App\Services\PaymentService::class)->listTransactions($r->user()->id,$enterpriseId,$d['type']??null);
   return response()->json(['ok'=>true,'transactions'=>$transactions],200);
 }
 public function stripeWebhook(Request $r){
   $result=app(\App\Services\PaymentService::class)->handleWebhook(
     (string)$r->header('Stripe-Signature',''),$r->getContent()
   );
   if(!$result['success']){
     $status=$result['error']==='missing_config'?503:400;
     return response()->json(['ok'=>false,'error'=>$result['error']],$status);
   }
   return response()->json(['ok'=>true]);
 }
 public function readNotice(Request $r,string $id){$r->user()->notifications()->where('id',$id)->firstOrFail()->markAsRead();return $r->is('api/*')?['ok'=>true]:back();}
   public function profile(Request $r){
    if($r->user()->role!=='participant'){
     $d=$r->validate(['name'=>'required|string|max:100','phone'=>'nullable|string|max:40','district'=>'nullable|string|max:100','expertise'=>'nullable|string|max:255','bio'=>'nullable|string|max:5000']);
     $r->user()->update($d);return $r->is('api/*')?['ok'=>true]:back()->with('success','Profile updated.');
    }
     $d=$r->validate(['name'=>'required|string|max:100','phone'=>'required|string|max:40','selected_course_id'=>'required|integer|exists:courses,id','gender'=>'required|in:Female,Male,Other,Prefer not to say','location'=>'required|string|max:255','urban_rural'=>'required|in:Urban,Rural','learner_age'=>'required|integer|min:10|max:100','refugee'=>'required|boolean','settlement_id'=>'exclude_unless:refugee,1|required|integer|exists:mel_settlements,id','pwd'=>'required|boolean','impairment'=>'exclude_unless:pwd,1|required|in:Physical,Visual,Hearing,Speech,Intellectual,Psychosocial,Multiple,Other','education_level'=>"required|in:No formal education,Primary,O-Level,A-Level,Certificate,Diploma,Bachelor's degree,Postgraduate,Other",'employed'=>'required|boolean','employer_name'=>'exclude_unless:employed,1|required|string|max:255','transformation_objective'=>'nullable|string|max:5000']);
     $samePaidCourse=$r->user()->learning_access_paid&&(int)$r->user()->selected_course_id===(int)$d['selected_course_id'];
     abort_unless($samePaidCourse||DB::table('courses')->where('id',$d['selected_course_id'])->where('status','published')->exists(),422,'Select an available course.');
     abort_unless(!$r->user()->learning_access_paid||$samePaidCourse,422,'Contact your programme administrator to change your paid course selection.');
     $d['settlement']=!empty($d['settlement_id'])?DB::table('mel_settlements')->where('id',$d['settlement_id'])->where('active',true)->value('name'):null;
     abort_unless(empty($d['settlement_id'])||$d['settlement']!==null,422,'Select a current settlement.');
     unset($d['settlement_id']);$d['pwd']=(bool)$d['pwd'];$d['refugee']=(bool)$d['refugee'];
     if(!$d['pwd'])$d['impairment']=null;
     $d['employed']=(bool)$d['employed'];if(!$d['employed'])$d['employer_name']=null;
     $d['learner_status']=$r->user()->learning_access_paid?'Active':'Pending Payment';$d['profile_complete']=true;$r->user()->update($d);return $r->is('api/*')?['ok'=>true]:back()->with('success','Learner profile saved.');
   }
  public function resource(Request $r,string $id){
    $item=DB::table('resources')->find($id);abort_unless($item,404);
    if($r->user()->role==='participant'){abort_unless($item->status==='published',404);\App\Services\LearningAccess::requireCourse($r->user(),(int)$item->course_id);abort_unless(\App\Services\LearningAccess::resourceAllowed($r->user(),$item),403,'Complete the preceding lessons to unlock this resource.');}
    else abort_unless($r->user()->manager()||\App\Services\Catalog::query('courses')->whereKey($item->course_id)->assignedToInstructor((int)$r->user()->id)->exists(),403);
    return $this->inlineResource($item);
  }
  public function lessonMedia(Request $r,string $id){
    $item=DB::table('lessons')->find($id);abort_unless($item,404);
    if($r->user()->role==='participant'){abort_unless($item->status==='published',404);\App\Services\LearningAccess::requireCourse($r->user(),(int)$item->course_id);abort_unless(\App\Services\LearningAccess::lessonAllowed($r->user(),$item),403);}
    else abort_unless($r->user()->manager()||\App\Services\Catalog::query('courses')->whereKey($item->course_id)->assignedToInstructor((int)$r->user()->id)->exists(),403);
    return $this->inlineResource($item);
  }
  private function inlineResource(object $item){
    if(($item->content_source??null)==='url'&&!empty($item->content_url))return redirect()->away($item->content_url)->header('Cache-Control','private, no-store');
    if(($item->content_source??null)==='write')return response($item->content_body??'',200,['Content-Type'=>'text/plain; charset=UTF-8','Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff']);
    abort_unless($item->file_path&&\Illuminate\Support\Facades\Storage::disk('local')->exists($item->file_path),404);
    $path=\Illuminate\Support\Facades\Storage::disk('local')->path($item->file_path);
    $mime=function_exists('mime_content_type')?mime_content_type($path):false;
    return response()->file($path,['Content-Type'=>$mime?:'application/octet-stream','Content-Disposition'=>'inline; filename="'.basename($path).'"','Cache-Control'=>'private, no-store, no-cache, must-revalidate','X-Content-Type-Options'=>'nosniff','X-Frame-Options'=>'SAMEORIGIN']);
  }
  public function download(Request $r,string $type,string $id){
   abort_unless(in_array($type,['resources','submissions']),404);$item=DB::table($type)->find($id);abort_unless($item,404);
   if($type==='resources'){
     if($r->user()->role==='participant') {abort_unless($item->status==='published',404);\App\Services\LearningAccess::requireCourse($r->user(),(int)$item->course_id);abort_unless(\App\Services\LearningAccess::resourceAllowed($r->user(),$item),403,'Complete the preceding lessons to unlock this resource.');}
     else abort_unless($r->user()->manager()||\App\Services\Catalog::query('courses')->whereKey($item->course_id)->assignedToInstructor((int)$r->user()->id)->exists(),403);
   }else{
    $a=DB::table('assignments')->find($item->assignment_id);
    abort_unless($r->user()->id===$item->user_id||$r->user()->manager()||\App\Services\Catalog::query('courses')->whereKey($a->course_id)->assignedToInstructor((int)$r->user()->id)->exists(),403);
   }
    if($type==='resources')return $this->inlineResource($item);abort_unless($item->file_path,404);return \Illuminate\Support\Facades\Storage::disk('local')->download($item->file_path);
 }
  public function certificate(Request $r,string $id){
    abort_unless($r->user()->role==='participant',403);\App\Services\LearningAccess::requireCourse($r->user(),(int)$id);
   Workflow::enrolled($r->user(),$id);$c=DB::table('courses')->find($id);
    $lessons=\App\Services\LearningAccess::lessons((int)$id)->pluck('id');
   abort_unless($lessons->count()>0&&DB::table('lesson_progress')->where('user_id',$r->user()->id)->whereIn('lesson_id',$lessons)->count()===$lessons->count(),422,'Complete all published lessons first.');
   $assignments=DB::table('assignments')->where('course_id',$id)->where('status','published')->pluck('id');
   abort_unless(DB::table('submissions')->where('user_id',$r->user()->id)->whereIn('assignment_id',$assignments)->where('status','passed')->count()===$assignments->count(),422,'Pass all published practical assignments first.');
    $certificate=DB::table('course_certificates')->where('course_id',$id)->where('user_id',$r->user()->id)->first();abort_unless($certificate,403,'Awaiting your assigned instructor completion recommendation.');
    return view('portal.certificate',['course'=>$c,'user'=>$r->user(),'certificate'=>$certificate]);
 }
}
