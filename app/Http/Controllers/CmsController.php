<?php
namespace App\Http\Controllers;
use App\Services\Catalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
class CmsController extends Controller {
 private function access(Request $r,$module){abort_unless(Catalog::allowed($r->user(),$module),403);}
 public function index(Request $r,string $module){$this->access($r,$module);$q=Catalog::scope(Catalog::query($module),$r->user(),$module);$field=$module==='users'?'name':($module==='settings'?'key':'title');if($s=$r->query('q'))$q->where($field,'like','%'.$s.'%');return view('admin.index',['module'=>$module,'meta'=>config("modules.$module"),'rows'=>$q->latest()->paginate(20)->withQueryString()]);}
 public function form(Request $r,string $module,?string $id=null){$this->access($r,$module);$record=$id?Catalog::scope(Catalog::query($module),$r->user(),$module)->findOrFail($id):null;return view('admin.form',['module'=>$module,'meta'=>config("modules.$module"),'record'=>$record,'options'=>Catalog::options($module,$r->user())]);}
 public function save(Request $r,string $module,?string $id=null){
  $this->access($r,$module);$record=$id?Catalog::scope(Catalog::query($module),$r->user(),$module)->findOrFail($id):Catalog::model($module);
  $d=$r->validate(Catalog::rules($module,$id));
  if(!$r->user()->manager()){
   if($module==='courses')$d['instructor_id']=$r->user()->id;
   elseif($module==='slots')$d['mentor_id']=$r->user()->id;
   else abort_unless(Catalog::query('courses')->where('instructor_id',$r->user()->id)->where('id',$d['course_id'])->exists(),403);
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
  if(isset($d['file'])){$d['file_path']=$d['file']->store('resources','local');unset($d['file']);}
  $record->fill($d)->save();
  DB::table('audit_logs')->insert(['user_id'=>$r->user()->id,'module'=>$module,'action'=>$id?'update':'create','record_id'=>$record->id,'created_at'=>now(),'updated_at'=>now()]);
  if($module==='announcements'&&$record->status==='published')\App\Models\User::where('status','active')->chunkById(100,function($users)use($record){foreach($users as $u)$u->notify(new \App\Notifications\PlatformNotice($record->title,$record->body));});
  return redirect('/admin/'.$module)->with('success','Saved successfully.');
 }
 public function delete(Request $r,string $module,string $id){$this->access($r,$module);$record=Catalog::scope(Catalog::query($module),$r->user(),$module)->findOrFail($id);if($module==='users'&&($record->id===$r->user()->id||$record->role==='admin'))return back()->withErrors(['delete'=>'Administrators cannot be deleted here.']);try{$record->delete();}catch(QueryException $e){if(in_array((string)$e->getCode(),['23000','23503']))return back()->withErrors(['delete'=>'This record is in use. Archive it using its status instead.']);throw $e;}DB::table('audit_logs')->insert(['user_id'=>$r->user()->id,'action'=>'delete','module'=>$module,'record_id'=>$id,'created_at'=>now(),'updated_at'=>now()]);return back()->with('success','Deleted successfully.');}
}
