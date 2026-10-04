<?php
namespace App\Services;
use App\Models\User;
use App\Notifications\PlatformNotice;
use Illuminate\Support\Facades\{DB,Validator};
use Illuminate\Validation\ValidationException;
class Workflow {
 public static function run(User $u,string $action,array $input): array {
  abort_unless($u->role==='participant',403,'Participant access required.');
  return DB::transaction(function()use($u,$action,$input){
   // Serialise actions for one participant. Also protects uniqueness and sync replay.
   User::whereKey($u->id)->lockForUpdate()->firstOrFail();
   $client=$input['client_id']??null;
   if($client){Validator::make(['client_id'=>$client],['client_id'=>'required|uuid'])->validate();
    $old=DB::table('sync_actions')->where('user_id',$u->id)->where('client_id',$client)->first();
    if($old){abort_unless($old->action===$action,409);return json_decode($old->response,true);}}
   $data=self::perform($u,$action,$input);
   if($client)DB::table('sync_actions')->insert(['user_id'=>$u->id,'client_id'=>$client,'action'=>$action,'response'=>json_encode($data),'created_at'=>now(),'updated_at'=>now()]);
   return $data;
  });
 }
 private static function perform(User $u,string $action,array $in): array {
  $rules=match($action){
   'enrol'=>['course_id'=>'required|integer|exists:courses,id'],
   'complete'=>['lesson_id'=>'required|integer|exists:lessons,id'],
   'submit'=>['assignment_id'=>'required|integer|exists:assignments,id','body'=>'required|string|max:20000','file'=>'nullable|file|mimes:pdf,txt,jpg,jpeg,png|max:10240'],
   'practice'=>['skill_id'=>'required|integer|exists:skills,id','title'=>'required|string|max:255','body'=>'required|string|max:20000','minutes'=>'required|integer|min:1|max:1440','practised_on'=>'required|date|before_or_equal:today'],
   'book'=>['slot_id'=>'required|integer|exists:slots,id','goal'=>'required|string|max:5000'],
   'cancel-booking'=>['booking_id'=>'required|integer|exists:bookings,id'],
   'apply'=>['opportunity_id'=>'required|integer|exists:opportunities,id','motivation'=>'required|string|max:10000'],
   'enterprise'=>['title'=>'required|string|max:255','sector'=>'required|string|max:100','idea'=>'required|string|max:20000','business_plan'=>'nullable|string|max:30000','stage'=>'required|in:idea,planning,operating,growing'],
   'income'=>['enterprise_id'=>'required|integer|exists:enterprises,id','type'=>'required|in:income,expense','amount'=>'required|numeric|min:0.01|max:9999999999.99','description'=>'required|string|max:255','occurred_on'=>'required|date|before_or_equal:today'],
   'register-event'=>['event_id'=>'required|integer|exists:events,id'],
   default=>abort(404)
  };
  $d=Validator::make($in,$rules)->validate();$id=null;
  switch($action){
   case 'enrol':
    self::published('courses',$d['course_id']);
    DB::table('enrolments')->updateOrInsert(['user_id'=>$u->id,'course_id'=>$d['course_id']],['updated_at'=>now(),'created_at'=>now()]);break;
   case 'complete':
    $lesson=self::published('lessons',$d['lesson_id']);self::enrolled($u,$lesson->course_id);
    DB::table('lesson_progress')->updateOrInsert(['user_id'=>$u->id,'lesson_id'=>$lesson->id],['completed_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);break;
   case 'submit':
    $a=self::published('assignments',$d['assignment_id']);self::enrolled($u,$a->course_id);
    if($a->due_at && now()->gt($a->due_at))self::invalid('assignment_id','This assignment deadline has passed.');
    $previous=DB::table('submissions')->where('user_id',$u->id)->where('assignment_id',$a->id)->first();
    if($previous && $previous->status!=='returned')self::invalid('assignment_id','This work has already been submitted. A reviewer must return it before resubmission.');
    $file=$d['file']??null;unset($d['file']);
    if($file)$d['file_path']=$file->store('submissions','local');
    DB::table('submissions')->updateOrInsert(['user_id'=>$u->id,'assignment_id'=>$a->id],$d+['status'=>'submitted','score'=>null,'feedback'=>null,'reviewer_id'=>null,'created_at'=>$previous?->created_at??now(),'updated_at'=>now()]);
    $course=DB::table('courses')->find($a->course_id);self::notify($course->instructor_id,'Practical work submitted',$u->name.' submitted '.$a->title);break;
   case 'practice':
    self::published('skills',$d['skill_id']);$id=self::insert('practice_logs',$d+['user_id'=>$u->id]);break;
   case 'book':
    $slot=DB::table('slots')->where('id',$d['slot_id'])->lockForUpdate()->first();
    if($slot->status!=='open'||now()->gte($slot->starts_at)||DB::table('bookings')->where('slot_id',$slot->id)->exists())self::invalid('slot_id','This mentorship slot is unavailable.');
    $mentor=User::findOrFail($slot->mentor_id);abort_unless($mentor->status==='active'&&$mentor->role==='mentor',422);
    $id=self::insert('bookings',$d+['user_id'=>$u->id]);self::notify($slot->mentor_id,'New mentorship request',$u->name.' requested '.$slot->title);break;
   case 'cancel-booking':
    $b=DB::table('bookings')->where('user_id',$u->id)->where('id',$d['booking_id'])->first();abort_unless($b,404);
    if(!in_array($b->status,['requested','confirmed']))self::invalid('booking_id','This booking cannot be cancelled.');
    DB::table('bookings')->where('id',$b->id)->delete();break;
   case 'apply':
    $o=self::published('opportunities',$d['opportunity_id']);
    if(now()->startOfDay()->gt($o->deadline))self::invalid('opportunity_id','Applications are closed.');
    if(DB::table('applications')->where('user_id',$u->id)->where('opportunity_id',$o->id)->exists())self::invalid('opportunity_id','You have already applied.');
    $id=self::insert('applications',$d+['user_id'=>$u->id]);break;
   case 'enterprise':$id=self::insert('enterprises',$d+['user_id'=>$u->id]);break;
   case 'income':
    abort_unless(DB::table('enterprises')->where('user_id',$u->id)->where('id',$d['enterprise_id'])->exists(),403);
    $id=self::insert('transactions',$d+['user_id'=>$u->id]);break;
   case 'register-event':
    $e=DB::table('events')->where('id',$d['event_id'])->lockForUpdate()->first();
    if($e->status!=='published'||now()->gte($e->starts_at))self::invalid('event_id','Event registration is closed.');
    if(DB::table('event_registrations')->where('user_id',$u->id)->where('event_id',$e->id)->exists())self::invalid('event_id','You are already registered.');
    if($e->capacity>0&&DB::table('event_registrations')->where('event_id',$e->id)->where('status','!=','cancelled')->count()>=$e->capacity)self::invalid('event_id','This event is full.');
    $id=self::insert('event_registrations',$d+['user_id'=>$u->id]);break;
  }
  DB::table('audit_logs')->insert(['user_id'=>$u->id,'action'=>$action,'module'=>'participant','record_id'=>$id,'created_at'=>now(),'updated_at'=>now()]);
  return ['ok'=>true,'id'=>$id,'message'=>'Saved successfully.'];
 }
 public static function insert($table,$data){return DB::table($table)->insertGetId($data+['created_at'=>now(),'updated_at'=>now()]);}
 public static function published($table,$id){$r=DB::table($table)->where('id',$id)->where('status','published')->first();abort_unless($r,404);return $r;}
 public static function enrolled(User $u,$course){self::published('courses',$course);abort_unless(DB::table('enrolments')->where('user_id',$u->id)->where('course_id',$course)->exists(),403,'Please enrol in this course first.');}
 public static function invalid($field,$message){throw ValidationException::withMessages([$field=>$message]);}
 public static function notify($id,$title,$body){User::find($id)?->notify(new PlatformNotice($title,$body));}
}
