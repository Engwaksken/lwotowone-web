<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Storage};

class MelController extends Controller
{
 private const CATEGORIES=['teachers'=>['Teachers / educators',['date','Date','date'],['name','Name','text'],['gender','Gender','text'],['age','Age','number'],['refugee','Refugee','text'],['host_community','Host Community','text'],['urban_rural','Urban/Rural','text'],['pwd','PWD','text']], 'schools'=>['Schools',['date','Date','date'],['school','School / Institution','text'],['urban_rural','Urban/Rural','text'],['ownership','Ownership','text'],['level','Level','text']], 'other_users'=>['Other users',['date','Date','date'],['gender','Gender','text'],['host_community','Host Community','text'],['urban_rural','Urban/Rural','text'],['pwd','PWD','text'],['education','Education','text']], 'employment'=>['Employment',['date','Date','date'],['name','Name','text'],['gender','Gender','text'],['age','Age','number'],['pwd','PWD','text'],['type','Type','text'],['role','Role','text'],['status','Status','text']], 'finance'=>['Finance',['date','Date','date'],['amount','Amount USD','number'],['form','Form','text'],['source','Source','text']], 'partnerships'=>['Partnerships',['date','Date','date'],['partner','Partner','text'],['type','Type','text'],['status','Status','text']], 'revenue'=>['Revenue',['month','Month','text'],['gross_revenue','Gross Revenue (UGX)','number'],['stream','Stream','text']]];
  private function authorize(Request $r): void {abort_unless($r->user()->manager(),403);}
  public function enrollment(Request $r){
   $this->authorize($r);$filters=$r->validate(['status'=>'nullable|in:all,pending,enrolled,incomplete','q'=>'nullable|string|max:255']);$filters+=['status'=>'all','q'=>''];$filters['status']=$filters['status']??'all';
   $learners=DB::table('users')->where('role','participant');
   if($filters['status']==='pending')$learners->where('profile_complete',true)->where('learning_access_paid',false);
   elseif($filters['status']==='enrolled')$learners->where('learning_access_paid',true);
   elseif($filters['status']==='incomplete')$learners->where('profile_complete',false);
   if($filters['q']){$pattern='%'.str_replace(['!','%','_'],['!!','!%','!_'],$filters['q']).'%';$learners->where(fn($q)=>$q->whereRaw("name LIKE ? ESCAPE '!'",[$pattern])->orWhereRaw("email LIKE ? ESCAPE '!'",[$pattern]));}
    return view('admin.enrollment',['cohorts'=>DB::table('mel_cohorts')->orderBy('name')->get(),'settlements'=>DB::table('mel_settlements')->orderBy('name')->get(),'pendingLearners'=>(clone $learners)->where('learning_access_paid',false)->whereNotNull('selected_course_id')->latest()->get(),'outcomeLearners'=>$learners->latest()->limit(500)->get(),'filters'=>$filters,'trialLearners'=>DB::table('enrolments')->join('users','users.id','=','enrolments.user_id')->join('courses','courses.id','=','enrolments.course_id')->whereNotNull('enrolments.trial_started_at')->whereNull('enrolments.payment_confirmed_at')->select('enrolments.*','users.name','users.email','courses.title')->get()]);
  }
  public function index(Request $r){$this->authorize($r);$counts=[];$learners=DB::table('users')->where('role','participant');$total=(clone $learners)->count();$counts['Total Learners Reached']=$total;$counts['Female New Learners']=($total?round((clone $learners)->where('gender','Female')->count()*100/$total,1):0).'%';$counts['Refugees']=(clone $learners)->where('refugee',true)->count();$counts['PWDs']=(clone $learners)->where('pwd',true)->count();$counts['New Learners']=(clone $learners)->whereYear('created_at',now()->year)->count();$counts['Teachers/Educators Reached']=DB::table('mel_records')->where('category','teachers')->count();$counts['Schools reached']=DB::table('mel_records')->where('category','schools')->count();$counts['Other users']=DB::table('mel_records')->where('category','other_users')->count();$counts['Employees']=(clone $learners)->where('employed',true)->count()+DB::table('mel_records')->where('category','employment')->count();$rows=DB::table('mel_records')->get();$amount=fn($category,$field)=>(float)$rows->where('category',$category)->sum(fn($r)=>(float)(json_decode($r->data,true)[$field]??0));$counts['Finance Raised (USD)']=number_format($amount('finance','amount'),2);$counts['Partnerships']=DB::table('mel_records')->where('category','partnerships')->count();$counts['Cumulative Revenue']=number_format($amount('revenue','gross_revenue'));$counts['Youth in Work']=(clone $learners)->where('employed',true)->count();$genderChart=(clone $learners)->whereNotNull('gender')->select('gender',DB::raw('COUNT(*) as total'))->groupBy('gender')->orderBy('gender')->get()->map(fn($row)=>['label'=>$row->gender,'value'=>(int)$row->total])->all();$inclusionChart=[['label'=>'Learners with disabilities','value'=>(clone $learners)->where('pwd',true)->count()],['label'=>'Refugee learners','value'=>(clone $learners)->where('refugee',true)->count()],['label'=>'Currently employed','value'=>(clone $learners)->where('employed',true)->count()],['label'=>'Learners enrolled','value'=>(clone $learners)->where('learning_access_paid',true)->count()]];$participantRows=(clone $learners)->latest()->limit(500)->get();$rowsByCategory=$rows->sortByDesc('id')->groupBy('category');$quality=[
  ['label'=>'Profile not completed','value'=>(clone $learners)->where('profile_complete',false)->count(),'tab'=>'learners','hint'=>'Learners who have not finished their profile.'],
  ['label'=>'Missing gender','value'=>(clone $learners)->whereNull('gender')->count(),'tab'=>'learners','hint'=>'Needed for gender reporting.'],
  ['label'=>'Missing age or location','value'=>(clone $learners)->where(fn($q)=>$q->whereNull('learner_age')->orWhereNull('location'))->count(),'tab'=>'learners','hint'=>'Needed for age and location reporting.'],
  ['label'=>'Enrolled without a learner number','value'=>(clone $learners)->where('learning_access_paid',true)->whereNull('learner_no')->count(),'tab'=>'learners','hint'=>'Assign numbers from Enrollment and cohorts.','href'=>'/admin/enrollment'],
  ['label'=>'Employed without an after-work status','value'=>(clone $learners)->where('employed',true)->whereNull('after_work_status')->count(),'tab'=>'learners','hint'=>'Needed for employment outcomes.'],
];
$documents=DB::table('mel_documents')->orderByDesc('id')->limit(100)->get();
return view('admin.mel',['categories'=>self::CATEGORIES,'records'=>$rowsByCategory,'learners'=>$participantRows,'counts'=>$counts,'genderChart'=>$genderChart,'inclusionChart'=>$inclusionChart,'quality'=>$quality,'documents'=>$documents]);}
 public function save(Request $r,string $category){$this->authorize($r);abort_unless(isset(self::CATEGORIES[$category]),404);$rules=[];foreach(array_slice(self::CATEGORIES[$category],1) as [$key,$label,$type])$rules[$key]=['nullable',$type==='number'?'numeric':($type==='date'?'date':'string'),$type==='number'?'max:999999999999':'max:5000'];$data=$r->validate($rules);DB::table('mel_records')->insert(['category'=>$category,'data'=>json_encode($data),'created_at'=>now(),'updated_at'=>now()]);return back()->with('success','MEL record saved.');}
 public function delete(Request $r,string $category,string $id){$this->authorize($r);DB::table('mel_records')->where('category',$category)->where('id',$id)->delete();return back()->with('success','MEL record deleted.');}
 public function upload(Request $r){$this->authorize($r);$d=$r->validate(['category'=>'required|string|max:100','document'=>'required|string|max:255','description'=>'nullable|string|max:5000','file'=>'required|file|max:20480']);$file=$d['file'];DB::table('mel_documents')->insert(['category'=>$d['category'],'document'=>$d['document'],'description'=>$d['description']??null,'file_path'=>$file->store('mel-documents','local'),'size'=>$file->getSize(),'uploaded_by'=>$r->user()->id,'created_at'=>now(),'updated_at'=>now()]);return back()->with('success','Supporting document uploaded.');}
 public function deleteDocument(Request $r,string $id){$this->authorize($r);$doc=DB::table('mel_documents')->where('id',$id)->first();abort_unless($doc,404);Storage::disk('local')->delete($doc->file_path);DB::table('mel_documents')->where('id',$id)->delete();return back()->with('success','Document deleted.');}
 public function downloadDocument(Request $r,string $id){$this->authorize($r);$doc=DB::table('mel_documents')->where('id',$id)->first();abort_unless($doc,404);abort_unless(Storage::disk('local')->exists($doc->file_path),404);return Storage::disk('local')->download($doc->file_path,$doc->document);}
  public function confirmPayment(Request $r,string $id){
   $this->authorize($r);$d=$r->validate(['cohort_id'=>'required|integer|exists:mel_cohorts,id','course_id'=>'nullable|integer|exists:courses,id']);
   abort_unless(DB::table('users')->where('id',$id)->where('role','participant')->exists(),404);
   if(!empty($d['course_id']))abort_unless(DB::table('enrolments')->where('user_id',$id)->where('course_id',$d['course_id'])->exists(),422,'The learner is not enrolled in this course.');
   app(\App\Services\Enrollment::class)->confirm((int)$id,(int)$d['cohort_id'],(int)$r->user()->id,!empty($d['course_id'])?(int)$d['course_id']:null);
   return back()->with('success','Payment confirmed, cohort assigned, learner number generated, and course access activated.');
  }
  public function enrollmentTemplate(Request $r){
   $this->authorize($r);
   return response()->streamDownload(function(){$output=fopen('php://output','w');fputcsv($output,['email','cohort_id'],',','"','');fclose($output);},'enrollment-template.csv',['Content-Type'=>'text/csv; charset=UTF-8']);
  }
  public function importEnrollment(Request $r){
   $this->authorize($r);$data=$r->validate(['csv'=>'required|file|extensions:csv|mimes:csv,txt|max:5120','cohort_id'=>'nullable|integer|exists:mel_cohorts,id']);
   $result=app(\App\Services\Enrollment::class)->import($r->file('csv')->getRealPath(),isset($data['cohort_id'])?(int)$data['cohort_id']:null,(int)$r->user()->id);
   return redirect('/admin/enrollment#enrollment-panel-payments')->with('success',"{$result['enrolled']} learners enrolled; {$result['skipped']} already enrolled learners skipped.");
  }
  public function bulkEnrollment(Request $r){
   $this->authorize($r);$data=$r->validate(['cohort_id'=>'required|integer|exists:mel_cohorts,id','learner_ids'=>'required|array|min:1|max:1000','learner_ids.*'=>'required|integer|distinct|exists:users,id']);
   $count=DB::transaction(function()use($data,$r){$count=0;foreach($data['learner_ids'] as $id)if(app(\App\Services\Enrollment::class)->confirm((int)$id,(int)$data['cohort_id'],(int)$r->user()->id))$count++;return $count;});
   return redirect('/admin/enrollment#enrollment-panel-payments')->with('success',"$count learners enrolled.");
  }
 public function updateLearner(Request $r,string $id){$this->authorize($r);$learner=DB::table('users')->where('id',$id)->where('role','participant')->first();abort_unless($learner,404);$d=$r->validate(['verified_outcomes'=>'nullable|string|max:5000','other_verified_outcome'=>'nullable|string|max:5000','after_work_status'=>'nullable|in:Working,Self-employed,Studying,Seeking work,Other','after_work_pathway'=>'nullable|string|max:255']);DB::table('users')->where('id',$id)->update($d+['updated_at'=>now()]);DB::table('audit_logs')->insert(['user_id'=>$r->user()->id,'action'=>'update-outcomes','module'=>'learners','record_id'=>$id,'created_at'=>now(),'updated_at'=>now()]);return back()->with('success','Learner outcomes updated.');}
 public function saveCohort(Request $r,?string $id=null){$this->authorize($r);$d=$r->validate(['name'=>'required|string|max:120','enrollment_category'=>'required|string|max:100','learner_number_prefix'=>'required|string|max:32','learner_number_format'=>'required|string|max:100|regex:/^[A-Za-z0-9{}._-]+$/','next_sequence'=>'required|integer|min:1|max:999999999','sequence_padding'=>'required|integer|min:1|max:10','active'=>'nullable|boolean']);abort_unless(str_contains($d['learner_number_format'],'{sequence}'),422,'Number format must include the {sequence} token.');$d['active']=$r->boolean('active');$d['updated_at']=now();if($id){DB::table('mel_cohorts')->where('id',$id)->update($d);return back()->with('success','Cohort settings updated.');}$d['created_at']=now();DB::table('mel_cohorts')->insert($d);return back()->with('success','Learner cohort added.');}
 public function deleteCohort(Request $r,string $id){$this->authorize($r);abort_if(DB::table('users')->where('cohort_id',$id)->exists(),422,'This cohort has enrolled learners and cannot be removed.');DB::table('mel_cohorts')->where('id',$id)->delete();return back()->with('success','Cohort removed.');}
 public function addSettlement(Request $r){$this->authorize($r);$d=$r->validate(['name'=>'required|string|max:160|unique:mel_settlements,name']);DB::table('mel_settlements')->insert($d+['active'=>true,'created_at'=>now(),'updated_at'=>now()]);return back()->with('success','Settlement added.');}
 public function deleteSettlement(Request $r,string $id){$this->authorize($r);$settlement=DB::table('mel_settlements')->find($id);abort_unless($settlement,404);abort_if(DB::table('users')->where('settlement',$settlement->name)->exists(),422,'This settlement is assigned to a learner and cannot be removed.');DB::table('mel_settlements')->where('id',$id)->delete();return back()->with('success','Settlement removed.');}
  public function saveGateway(Request $r){
   $this->authorize($r);
   $types=config('payments.types');
   $r->validate(['provider_type'=>['required',\Illuminate\Validation\Rule::in(array_keys($types))]]);
   $fields=$types[$r->input('provider_type')];
   $rules=['name'=>'required|string|max:120','provider_type'=>'required|string','currency'=>'required|in:UGX,USD,EUR,GBP,KES','amount'=>'nullable|numeric|min:0|max:999999999999.99','instructions'=>($r->input('provider_type')==='Other'?'required':'nullable').'|string|max:5000','active'=>'nullable|boolean','configure_api'=>'nullable|boolean','api_environment'=>'nullable|in:sandbox,production'];
   foreach($fields as $key=>$field){
    if(($field['private']??false)&&!$r->boolean('configure_api'))continue;
    $required=$field['required']||(($field['api_required']??false)&&$r->boolean('configure_api'));
    $rules[$key]=[$required?'required':'nullable',($field['type']??'text')==='url'?'url:https':'string','max:'.match($key){'provider'=>120,'account_name'=>190,'account_number','merchant_code'=>100,default=>4096}];
   }
   $data=$r->validate($rules);$configuration=[];$credentials=[];
   foreach($fields as $key=>$field)if(!in_array($key,['provider','account_name','account_number','merchant_code'])){if($field['private']??false){if($r->boolean('configure_api'))$credentials[$key]=$data[$key]??null;}else $configuration[$key]=$data[$key]??null;unset($data[$key]);}
   if($data['provider_type']==='IOTEC')$data['provider']='IOTEC';
   if($credentials)$credentials['environment']=$data['api_environment']??'sandbox';
   unset($data['configure_api'],$data['api_environment']);
   $data['credentials']=$credentials?\Illuminate\Support\Facades\Crypt::encryptString(json_encode($credentials,JSON_THROW_ON_ERROR)):null;
   $data['configuration']=json_encode($configuration,JSON_THROW_ON_ERROR);$data['active']=$r->boolean('active');
   DB::table('payment_gateways')->insert($data+['created_at'=>now(),'updated_at'=>now()]);
   return redirect('/admin/site-settings#payment-methods')->with('success','Payment option saved.');
  }
 public function deleteGateway(Request $r,string $id){$this->authorize($r);DB::table('payment_gateways')->where('id',$id)->delete();return back()->with('success','Payment option deleted.');}
}
