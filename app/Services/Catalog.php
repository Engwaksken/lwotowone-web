<?php
namespace App\Services;
use App\Models\{Record,User};
use Illuminate\Validation\Rule;
class Catalog {
 public static function model(string $module){abort_unless(config("modules.$module"),404);return $module==='users' ? new User : Record::for($module);}
 public static function query(string $module){return self::model($module)->newQuery();}
 public static function allowed(User $u,string $module): bool {
  if($u->role==='admin')return true;
  if($u->role==='manager')return !in_array($module,['users','settings']);
   if($u->role==='instructor')return in_array($module,['courses','course_modules','lessons','assignments','resources']);
  return $u->role==='mentor' && $module==='slots';
 }
 public static function scope($query,User $u,string $module){
  if($u->manager())return $query;
  if($module==='slots')return $query->where('mentor_id',$u->id);
  if($module==='courses')return $query->where('instructor_id',$u->id);
  return $query->whereIn('course_id',self::query('courses')->where('instructor_id',$u->id)->select('id'));
 }
 public static function rules(string $module,$id=null): array {
  $rules=[];
  foreach(config("modules.$module.fields") as $field=>$type){
   $optional=is_string($type)&&str_starts_with($type,'optional:');
   if($optional)$type=substr($type,9);
   $r=[$optional?'nullable':'required'];
   if(is_array($type))$r[]=Rule::in($type);
   elseif(str_starts_with($type,'ref:')){$r[]='integer';$r[]=Rule::exists(substr($type,4),'id');}
   elseif(str_starts_with($type,'user:')){$r[]='integer';$r[]=Rule::exists('users','id')->where('role',substr($type,5))->where('status','active');}
   else $r=array_merge($r,match($type){
    'email'=>['email','max:190',Rule::unique($module,$field)->ignore($id)],
    'password'=>['string','min:12','max:128'],
    'slug'=>['string','max:150','regex:/^[a-z0-9_-]+$/',Rule::unique($module,$field)->ignore($id)],
    'number'=>['integer','min:0','max:100000'],
    'date','datetime'=>['date'],
    'url'=>['url:http,https','max:1000'],
     'file'=>$module==='resources'?['file','mimes:pdf,txt,jpg,jpeg,png,webp,mp4,webm,ogg','max:102400']:['file','mimes:pdf,txt,jpg,jpeg,png','max:10240'],
    'textarea'=>['string','max:50000'], default=>['string','max:255']});
   $rules[$field]=$r;
  }
  if($module==='resources' && $id)$rules['file'][0]='nullable';
  if(in_array($module,['slots','events']))$rules['ends_at'][]='after:starts_at';
  if($module==='assignments')$rules['pass_mark'][]='max:100';
  if($module==='users'&&!$id)$rules['password'][0]='required';
  return $rules;
 }
 public static function options(string $module,User $u): array {
  $out=[];
   foreach(config("modules.$module.fields") as $f=>$t){
    if(is_string($t)&&str_starts_with($t,'optional:'))$t=substr($t,9);
   if(is_array($t))$out[$f]=array_combine($t,$t);
    elseif(str_starts_with($t,'ref:')){ $table=substr($t,4);$q=self::query($table);if(!$u->manager()&&in_array($table,['courses','lessons','course_modules']))$q=self::scope($q,$u,$table);$out[$f]=$q->pluck('title','id')->all(); }
   elseif(str_starts_with($t,'user:'))$out[$f]=User::where('role',substr($t,5))->where('status','active')->pluck('name','id')->all();
  }return $out;
 }
}
