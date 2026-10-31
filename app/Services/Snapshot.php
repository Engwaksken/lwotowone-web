<?php
namespace App\Services;
use App\Models\User;
use Illuminate\Support\Facades\DB;
class Snapshot {
 public static function get(User $u): array {
   $out=['user'=>$u->only(['id','name','email','phone','district','role','preferred_language','selected_course_id','gender','location','urban_rural','learner_age','refugee','settlement','pwd','impairment','education_level','employed','employer_name','transformation_objective','profile_complete','learning_access_paid']),'synced_at'=>now()->toIso8601String()];
   $out['user']['profile_photo_url']=$u->profile_photo_path?url('/api/profile/photo?version='.$u->updated_at?->timestamp):null;
  $out['user']['profile_complete']=(bool)$u->profile_complete;$out['user']['learning_access_paid']=(bool)$u->learning_access_paid;
  $freeEnds=FreeAccess::endsAt();
  $out['access']=['free_access'=>FreeAccess::active(),'free_access_ends_at'=>$freeEnds?->toIso8601String(),'learning_open'=>$u->role!=='participant'||((bool)$u->profile_complete&&$u->learningAccessOpen()),'profile_complete'=>(bool)$u->profile_complete,'profile_percent'=>ProfileProgress::percent($u),'profile_missing'=>ProfileProgress::missing($u)];
  $out['settlements']=DB::table('mel_settlements')->where('active',true)->orderBy('name')->get(['id','name']);
   foreach(['programs','courses','skills','opportunities','events','posts','announcements'] as $t){$q=DB::table($t)->where('status','published');if($t==='opportunities')$q->whereDate('deadline','>=',today());$rows=$q->orderByDesc('id')->get();if($u->preferred_language==='lg'){$translations=match($t){'programs'=>['title'=>'title_lg','summary'=>'summary_lg','body'=>'body_lg'],'courses'=>['title'=>'title_lg','summary'=>'summary_lg'],default=>[]};$rows->each(fn($row)=>LearnerContent::useLuganda($row,$translations));}$out[$t]=$rows;}
   $ids=DB::table('enrolments')->where('user_id',$u->id)->pluck('course_id');$access=[];$lessonAccess=[];$lessonOrder=[];$done=DB::table('lesson_progress')->where('user_id',$u->id)->pluck('lesson_id');
   foreach($ids as $courseId){$access[$courseId]=LearningAccess::allowed($u,(int)$courseId);$blocked=false;foreach(LearningAccess::lessons((int)$courseId,LearningAccess::language($u)) as $index=>$lesson){$lessonAccess[$lesson->id]=$access[$courseId]&&!$blocked;$lessonOrder[$lesson->id]=[$index,$lesson->module_title];if(!$done->contains($lesson->id))$blocked=true;}}
    foreach(['lessons','assignments','resources'] as $t)$out[$t]=DB::table($t)->whereIn('course_id',$ids)->where('status','published')->where('content_language',LearningAccess::language($u))->whereIn('course_id',DB::table('courses')->where('status','published')->select('id'))->orderBy('id')->get()->map(function($r)use($t,$access,$lessonAccess,$lessonOrder,$u){
     if($u->preferred_language==='lg'){$translations=match($t){'lessons'=>['title'=>'title_lg','body'=>'body_lg','content_body'=>'content_body_lg','module_title'=>'module_title_lg'],'assignments'=>['title'=>'title_lg','instructions'=>'instructions_lg'],'resources'=>['title'=>'title_lg','description'=>'description_lg','content_body'=>'content_body_lg']};LearnerContent::useLuganda($r,$translations);}
      if($t==='lessons')$r->language_unavailable=$u->preferred_language==='en'&&($r->content_language??'en')==='lg'&&empty($r->title);
     $attachmentType=match($t){'lessons'=>'lesson','assignments'=>'assignment',default=>null};
     if($attachmentType)$r->attachments=DB::table('learning_attachments')->where('entity_type',$attachmentType)->where('entity_id',$r->id)->get(['id','original_name'])->map(fn($file)=>['id'=>$file->id,'name'=>$file->original_name,'url'=>url('/files/attachments/'.$file->id)]);
     // Files the learner may ask to save; saving itself needs an approved download request.
     if($t!=='assignments')$r->downloadable=($r->file_path&&LearningContent::source($r,$t)==='upload')||($t==='lessons'&&collect($r->attachments??[])->isNotEmpty());
    $r->locked=!($access[$r->course_id]??false);
    if($t==='lessons'){$r->locked=!($lessonAccess[$r->id]??false);$r->sort_order=$lessonOrder[$r->id][0]??PHP_INT_MAX;$r->module_title=$lessonOrder[$r->id][1]??null;$r->media_type=LearningContent::mediaType($r);$r->viewer_url=!$r->locked&&$r->file_path?url('/learning/content/lessons/'.$r->id):null;$r->download_url=$r->viewer_url?url('/api/lessons/'.$r->id.'/download'):null;unset($r->file_path);if($r->locked){$r->body=null;$r->video_url=null;$r->content_body=null;$r->content_url=null;}}
    if($t==='assignments'&&$r->locked)$r->instructions=null;
    if($t==='resources'){$r->locked=$r->locked||($r->lesson_id&&!($lessonAccess[$r->lesson_id]??false));$hasFile=(bool)$r->file_path&&!$r->locked;$r->media_type=LearningContent::mediaType($r);unset($r->file_path);$r->viewer_url=$hasFile?url('/learning/content/resources/'.$r->id):null;$r->download_url=$hasFile?url('/api/resources/'.$r->id.'/download'):null;if($r->locked){$r->content_body=null;$r->content_url=null;}}
    return $r;
   })->filter(fn($r)=>match($t){'lessons'=>isset($lessonOrder[$r->id]),'resources'=>!$r->lesson_id||isset($lessonOrder[$r->lesson_id]),default=>true})->values();
  $out['slots']=DB::table('slots')->where('status','open')->where('starts_at','>',now())->whereIn('mentor_id',User::where('role','mentor')->where('status','active')->select('id'))->whereNotIn('id',DB::table('bookings')->select('slot_id'))->get();
  $out['mentors']=User::where('role','mentor')->where('status','active')->get(['id','name','bio','expertise']);
  foreach(['enrolments','lesson_progress','submissions','practice_logs','bookings','applications','enterprises','transactions','event_registrations'] as $t)$out[$t]=DB::table($t)->where('user_id',$u->id)->orderByDesc('id')->get()->map(function($r)use($t){if($t==='submissions')unset($r->file_path);if($t==='bookings'){$slot=DB::table('slots')->find($r->slot_id);$r->session_title=$slot?->title;$r->starts_at=$slot?->starts_at;$r->ends_at=$slot?->ends_at;$r->location=$slot?->location;$r->mode=$slot?->mode;$r->mentor_name=User::find($slot?->mentor_id)?->name;}return $r;});
  $currency=DB::table('payment_gateways')->where('active',true)->orderBy('name')->value('currency')??'UGX';$out['events']=$out['events']->map(function($event)use($currency){$event->registration_open=now()->lt($event->starts_at);$event->fee_due=(float)($event->payment_amount??0)>0&&!FreeAccess::active();$event->currency=$currency;return $event;});
   $out['course_progress']=LearningProgress::fromSnapshot($out);
   $out['course_access']=collect($out['enrolments'])->map(fn($row)=>['course_id'=>$row->course_id,'allowed'=>$access[$row->course_id]??false,'trial_expires_at'=>$row->trial_expires_at,'paid'=>(bool)$row->payment_confirmed_at])->all();
   $out['enrollment_requests']=DB::table('enrollment_requests')->where('user_id',$u->id)->get(['course_id','status','message','decision_note','updated_at']);
   $out['download_policy']=['valid_days'=>Downloads::VALID_DAYS,'max_downloads'=>Downloads::MAX_DOWNLOADS];
   $out['download_requests']=DB::table('download_requests')->where('user_id',$u->id)->get()->map(fn($row)=>['id'=>$row->id,'item_type'=>$row->item_type,'item_id'=>$row->item_id,'state'=>Downloads::state($row),'expires_at'=>$row->expires_at,'downloads_left'=>max(0,Downloads::MAX_DOWNLOADS-(int)$row->downloads_used),'decision_note'=>$row->decision_note,'download_url'=>Downloads::isActive($row)?url('/api/downloads/'.$row->id):null])->values();
   $out['certificates']=DB::table('course_certificates')->where('user_id',$u->id)->get(['course_id','reference','recommended_at']);
  $out['event_registrations']=$out['event_registrations']->map(function($r){$event=DB::table('events')->find($r->event_id);$r->event_title=$event?->title;$r->can_cancel=$r->status==='registered'&&$event&&now()->lt($event->starts_at);return $r;});
  $out['notifications']=$u->notifications()->latest()->limit(100)->get(['id','data','read_at','created_at']);
  // Until the profile is complete and access is open, the app only gets what it needs to finish onboarding.
  if(!$out['access']['learning_open']){$out['slots']=[];$out['mentors']=[];}
  return $out;
 }
}
