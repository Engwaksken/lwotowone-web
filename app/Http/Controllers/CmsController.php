<?php
namespace App\Http\Controllers;
use App\Services\Catalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Carbon\CarbonImmutable;
class CmsController extends Controller {
 public function siteSettings(Request $r){abort_unless($r->user()->manager(),403);$settings=DB::table('settings')->pluck('value','key')->all();$gateways=DB::table('payment_gateways')->orderBy('name')->get();$aiConfigured=app(\App\Services\AiAssistant::class)->configured();return view('admin.site-settings',compact('settings','gateways','aiConfigured'));}
 public function saveSiteSettings(Request $r){
  abort_unless($r->user()->role==='admin',403);
  $data=$r->validate([
   'primary_color'=>['required','regex:/^#[0-9A-Fa-f]{6}$/'],'accent_color'=>['required','regex:/^#[0-9A-Fa-f]{6}$/'],
   'font_family'=>['required','string','max:120','regex:/^[\pL\pN _-]+$/u'],'font_url'=>['nullable','url:https','max:1000'],'font_size'=>['required','integer','min:14','max:20'],
    'logo'=>['nullable','file','mimetypes:image/png,image/jpeg,image/webp','extensions:png,jpg,jpeg,webp','max:4096'],'favicon'=>['nullable','file','mimetypes:image/png,image/x-icon,image/vnd.microsoft.icon','extensions:png,ico','max:1024'],
  ]);
  foreach(['logo'=>'site_logo','favicon'=>'site_favicon'] as $field=>$key){
   if($r->hasFile($field)){$directory=public_path('site-branding');if(!is_dir($directory)&&!mkdir($directory,0755,true)&&!is_dir($directory))throw new \RuntimeException('The site branding upload directory could not be created.');$file=$r->file($field);$extension=strtolower($file->getClientOriginalExtension());$name=$key.'-'.bin2hex(random_bytes(8)).'.'.$extension;if(!$file->move($directory,$name))throw new \RuntimeException('The uploaded branding file could not be saved.');$data[$key]='/site-branding/'.$name;}
   unset($data[$field]);
  }
  foreach($data as $key=>$value){$exists=DB::table('settings')->where('key',$key)->exists();if($exists)DB::table('settings')->where('key',$key)->update(['value'=>(string)$value,'updated_at'=>now()]);else DB::table('settings')->insert(['key'=>$key,'value'=>(string)$value,'created_at'=>now(),'updated_at'=>now()]);}
  DB::table('audit_logs')->insert(['user_id'=>$r->user()->id,'module'=>'settings','action'=>'update','record_id'=>0,'created_at'=>now(),'updated_at'=>now()]);
  return back()->with('success','Website appearance updated.');
 }
 public function saveAiSettings(Request $r){
  abort_unless($r->user()->role==='admin',403);
   $data=$this->aiSettingsData($r);
   $saved=DB::table('settings')->pluck('value','key');
   if(empty($data['ai_api_key'])&&!$r->boolean('clear_api_key')&&$saved->get('ai_api_key')&&($saved->get('ai_provider','openai-compatible')!==$data['ai_provider']||rtrim($saved->get('ai_api_base_url',''),'/')!==rtrim($data['ai_api_base_url'],'/')))
    throw \Illuminate\Validation\ValidationException::withMessages(['ai_api_key'=>'Enter a new API key when changing the provider or API URL, or remove the saved key.']);
  foreach(['ai_provider','ai_api_base_url','ai_api_model'] as $key){$exists=DB::table('settings')->where('key',$key)->exists();if($exists)DB::table('settings')->where('key',$key)->update(['value'=>$data[$key],'updated_at'=>now()]);else DB::table('settings')->insert(['key'=>$key,'value'=>$data[$key],'created_at'=>now(),'updated_at'=>now()]);}
  if($r->boolean('clear_api_key'))DB::table('settings')->where('key','ai_api_key')->delete();
  elseif(!empty($data['ai_api_key'])){$encrypted='enc:'.\Illuminate\Support\Facades\Crypt::encryptString($data['ai_api_key']);$exists=DB::table('settings')->where('key','ai_api_key')->exists();if($exists)DB::table('settings')->where('key','ai_api_key')->update(['value'=>$encrypted,'updated_at'=>now()]);else DB::table('settings')->insert(['key'=>'ai_api_key','value'=>$encrypted,'created_at'=>now(),'updated_at'=>now()]);}
  DB::table('audit_logs')->insert(['user_id'=>$r->user()->id,'module'=>'settings','action'=>'update-ai','record_id'=>0,'created_at'=>now(),'updated_at'=>now()]);
  return redirect('/admin/site-settings#settings-panel-ai')->with('success','AI assistant and mentor matching settings saved.');
 }
 public function testAiConnection(Request $r){
  abort_unless($r->user()->role==='admin',403);
  $data=$this->aiSettingsData($r);
  return response()->json(app(\App\Services\AiAssistant::class)->testConnection($data));
 }
 private function aiSettingsData(Request $r): array {
  return $r->validate(['ai_provider'=>['required',\Illuminate\Validation\Rule::in(array_keys(config('ai.providers')))],'ai_api_base_url'=>'required|url:https|max:500','ai_api_model'=>'required|string|max:120','ai_api_key'=>'nullable|string|max:4096','clear_api_key'=>'nullable|boolean']);
 }
 private function access(Request $r,$module){abort_unless(Catalog::allowed($r->user(),$module),403);}
 public function index(Request $r,string $module){
   $this->access($r,$module);
   $statusOptions=config("modules.$module.fields.status",[]);if(!is_array($statusOptions))$statusOptions=[];
   $filters=$r->validate([
    'status'=>['nullable',\Illuminate\Validation\Rule::in(array_merge(['all'],$statusOptions))],
   'q'=>'nullable|string|max:255','period'=>'sometimes|required|in:all,week,month,year,custom',
   'start_date'=>'exclude_unless:period,custom|required|date_format:Y-m-d',
   'end_date'=>'exclude_unless:period,custom|required|date_format:Y-m-d|after_or_equal:start_date',
  ]);
   $filters+=['q'=>'','status'=>'all','period'=>'all','start_date'=>null,'end_date'=>null];$filters['q']=$filters['q']??'';$filters['status']=$filters['status']??'all';
   $q=Catalog::scope(Catalog::query($module),$r->user(),$module);
   if(in_array($module,['courses','programs']))$q->with('instructors');
   if($filters['status']!=='all')$q->where('status',$filters['status']);
  $field=$module==='users'?'name':($module==='settings'?'key':'title');
  if($filters['q']!==''){$search=str_replace(['!','%','_'],['!!','!%','!_'],$filters['q']);$q->whereRaw("{$field} LIKE ? ESCAPE '!'",['%'.$search.'%']);}
  if($filters['period']!=='all'){
   $today=CarbonImmutable::today(config('app.timezone'));
   [$start,$end]=match($filters['period']){
    'week'=>[$today->startOfWeek(CarbonImmutable::MONDAY),$today->endOfWeek(CarbonImmutable::SUNDAY)],
    'month'=>[$today->startOfMonth(),$today->endOfMonth()],
    'year'=>[$today->startOfYear(),$today->endOfYear()],
    'custom'=>[CarbonImmutable::parse($filters['start_date']),CarbonImmutable::parse($filters['end_date'])],
   };
   $q->whereBetween('created_at',[$start->startOfDay()->toDateTimeString(),$end->endOfDay()->toDateTimeString()]);
  }
   return view('admin.index',['module'=>$module,'meta'=>config("modules.$module"),'rows'=>$q->latest()->paginate(20)->withQueryString(),'options'=>Catalog::options($module,$r->user()),'filters'=>$filters,'statusOptions'=>$statusOptions]);
 }
 public function form(Request $r,string $module,?string $id=null){$this->access($r,$module);if($module==='courses'&&!$id)abort_unless($r->user()->manager(),403);$record=$id?Catalog::scope(Catalog::query($module),$r->user(),$module)->findOrFail($id):null;return view('admin.form',['module'=>$module,'meta'=>config("modules.$module"),'record'=>$record,'options'=>Catalog::options($module,$r->user())]);}
 public function save(Request $r,string $module,?string $id=null){
   $this->access($r,$module);$record=$id?Catalog::scope(Catalog::query($module),$r->user(),$module)->findOrFail($id):Catalog::model($module);
   $assignable=in_array($module,['courses','programs'],true);
   $assignmentChange=$assignable&&($r->exists('instructor_ids')||$r->exists('assignments_present')||$r->exists('lead_instructor_id')||$r->exists('instructor_id'));
   if($assignmentChange)abort_unless($r->user()->role==='admin',403);
   if($module==='courses'&&!$id)abort_unless($r->user()->manager(),403);
   $rules=Catalog::rules($module,$id);if($module==='courses')unset($rules['instructor_id']);
   $formattedContent=in_array($module,['lessons','resources'],true)&&$r->exists('content_format');
   if($formattedContent){unset($rules['body'],$rules['video_url'],$rules['file']);}
   $d=$r->validate($rules);
   if($module==='events'&&!$id)$d['registration_token']=(string)\Illuminate\Support\Str::uuid();
   if($formattedContent)$d=array_merge($d,\App\Services\LearningContent::validate($r,$module,$id?$record:null));
   $assignmentIds=[];$leadId=null;
   if($assignmentChange){
    $assignment=$r->validate(['instructor_ids'=>'sometimes|array','instructor_ids.*'=>'required|integer|min:1|distinct','lead_instructor_id'=>'nullable|integer|min:1','instructor_id'=>'nullable|integer|min:1']);
    $assignmentIds=array_map('intval',$assignment['instructor_ids']??($r->exists('assignments_present')?[]:(!empty($assignment['instructor_id'])?[$assignment['instructor_id']]:$record->assignedInstructorIds())));
    $leadId=isset($assignment['lead_instructor_id'])?(int)$assignment['lead_instructor_id']:null;
   }
   if($module==='courses'&&!empty($d['prerequisite_course_id'])){$previous=(int)$d['prerequisite_course_id'];$seen=[];while($previous){abort_if(isset($seen[$previous])||($id&&(int)$id===$previous),422,'Course prerequisites cannot form a cycle.');$seen[$previous]=true;$previous=(int)DB::table('courses')->where('id',$previous)->value('prerequisite_course_id');}}
   if($module==='lessons'&&!empty($d['module_id']))abort_unless(DB::table('course_modules')->where('id',$d['module_id'])->where('course_id',$d['course_id'])->exists(),422,'Select a module from this course.');
   if($module==='resources'&&!empty($d['lesson_id']))abort_unless(DB::table('lessons')->where('id',$d['lesson_id'])->where('course_id',$d['course_id'])->exists(),422,'Select a lesson from this course.');
  if(!$r->user()->manager()){
   if($module==='slots')$d['mentor_id']=$r->user()->id;
   elseif($module!=='courses')abort_unless(Catalog::query('courses')->assignedToInstructor((int)$r->user()->id)->whereKey($d['course_id'])->exists(),403);
  }
  if($module==='users'){
   if(empty($d['password']))unset($d['password']);else if($id)$record->tokens()->delete();
   if($id && $record->role==='admin' && ($d['role']!=='admin'||$d['status']!=='active') && Catalog::query('users')->where('role','admin')->where('status','active')->count()<=1)return back()->withErrors(['role'=>'Keep at least one active administrator.']);
  }
  if($module==='slots'){
   $overlap=DB::table('slots')->where('mentor_id',$d['mentor_id'])->where('starts_at','<',$d['ends_at'])->where('ends_at','>',$d['starts_at'])->when($id,fn($q)=>$q->where('id','!=',$id))->exists();
   if($overlap)return back()->withInput()->withErrors(['starts_at'=>'This overlaps another mentorship slot.']);
   if($id&&DB::table('bookings')->where('slot_id',$id)->exists())return back()->withErrors(['starts_at'=>'A booked session cannot be edited. Review the booking instead.']);
  }
  $oldFile=$record->file_path;$newFile=null;
  if(isset($d['file'])){$d['file_path']=$newFile=$d['file']->store($module==='lessons'?'lessons':'resources','local');unset($d['file']);}
  try{DB::transaction(function()use($record,$d,$assignmentChange,$assignmentIds,$leadId,$r,$module,$id){
   $record->fill($d)->save();
   if($assignmentChange)\App\Models\InstructorAssignments::sync($record,$assignmentIds,$leadId);
   DB::table('audit_logs')->insert(['user_id'=>$r->user()->id,'module'=>$module,'action'=>$id?'update':'create','record_id'=>$record->id,'created_at'=>now(),'updated_at'=>now()]);
  });}catch(\Throwable $e){if($newFile)\Illuminate\Support\Facades\Storage::disk('local')->delete($newFile);throw $e;}
  if($oldFile&&array_key_exists('file_path',$d)&&$oldFile!==$d['file_path'])\Illuminate\Support\Facades\Storage::disk('local')->delete($oldFile);
  if($module==='announcements'&&$record->status==='published')\App\Models\User::where('status','active')->chunkById(100,function($users)use($record){foreach($users as $u)$u->notify(new \App\Notifications\PlatformNotice($record->title,$record->body));});
  return redirect('/admin/'.$module)->with('success','Saved successfully.');
 }
 public function delete(Request $r,string $module,string $id){$this->access($r,$module);$record=Catalog::scope(Catalog::query($module),$r->user(),$module)->findOrFail($id);if($module==='users'&&($record->id===$r->user()->id||$record->role==='admin'))return back()->withErrors(['delete'=>'Administrators cannot be deleted here.']);try{$record->delete();}catch(QueryException $e){if(in_array((string)$e->getCode(),['23000','23503']))return back()->withErrors(['delete'=>'This record is in use. Archive it using its status instead.']);throw $e;}DB::table('audit_logs')->insert(['user_id'=>$r->user()->id,'action'=>'delete','module'=>$module,'record_id'=>$id,'created_at'=>now(),'updated_at'=>now()]);return back()->with('success','Deleted successfully.');}
}
