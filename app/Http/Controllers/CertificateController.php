<?php
namespace App\Http\Controllers;

use App\Models\User;
use App\Services\{CertificatePdf,LearningAccess,Workflow};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Storage};
use Illuminate\Validation\ValidationException;

class CertificateController extends Controller
{
    private function course(Request $r,string $id): object {$course=DB::table('courses')->find($id);abort_unless($course,404);abort_unless($r->user()->manager()||($r->user()->role==='instructor'&&$course->instructor_id===$r->user()->id),403);return $course;}
    public function index(Request $r) {
        abort_unless($r->user()->manager()||$r->user()->role==='instructor',403);$courses=DB::table('courses');if(!$r->user()->manager())$courses->where('instructor_id',$r->user()->id);
        $courses=$courses->get();$learners=DB::table('enrolments')->join('users','users.id','=','enrolments.user_id')->whereIn('enrolments.course_id',$courses->pluck('id'))->select('enrolments.course_id','users.id','users.name')->get();
        return view('admin.certificates',['courses'=>$courses,'learners'=>$learners,'certificates'=>DB::table('course_certificates')->whereIn('course_id',$courses->pluck('id'))->get()]);
    }
    public function editor(Request $r,string $id) {abort_unless($r->user()->role==='admin',403);$course=$this->course($r,$id);$template=DB::table('certificate_templates')->where('course_id',$id)->first();return view('admin.certificate-template',['course'=>$course,'template'=>$template,'placements'=>$template?json_decode($template->placements,true):CertificatePdf::defaults(),'fields'=>CertificatePdf::FIELDS]);}
    public function save(Request $r,string $id) {
        abort_unless($r->user()->role==='admin',403);$this->course($r,$id);$template=DB::table('certificate_templates')->where('course_id',$id)->first();
        $rules=['template'=>[$template?'nullable':'required','file','mimes:pdf,png,jpg,jpeg','extensions:pdf,png,jpg,jpeg','max:10240'],'placements'=>'required|array'];
        foreach(CertificatePdf::FIELDS as $field=>$label){$prefix='placements.'.$field;$rules[$prefix]='required|array';$rules[$prefix.'.enabled']='nullable|boolean';foreach(['x','y'] as $key)$rules[$prefix.'.'.$key]='required|numeric|min:0|max:95';$rules[$prefix.'.width']='required|numeric|min:5|max:100';$rules[$prefix.'.font_size']='required|numeric|min:8|max:48';$rules[$prefix.'.align']='required|in:L,C,R';}
        $data=$r->validate($rules);$placements=[];
        foreach(CertificatePdf::FIELDS as $field=>$label){$place=$data['placements'][$field];abort_unless($place['x']+$place['width']<=100,422,'Field width must fit within the template.');$place['enabled']=$r->boolean('placements.'.$field.'.enabled');$placements[$field]=array_intersect_key($place,array_flip(['x','y','width','font_size','align','enabled']));}
        $values=['placements'=>json_encode($placements,JSON_THROW_ON_ERROR),'updated_at'=>now()];$path=null;
        if($r->hasFile('template')){$file=$r->file('template');$format=strtolower($file->getClientOriginalExtension());if($format==='jpeg')$format='jpg';try{[$width,$height]=app(CertificatePdf::class)->size($file->getRealPath(),$format);}catch(\Throwable){throw ValidationException::withMessages(['template'=>'Cannot render this design. Use a single-page, unencrypted PDF (PDF 1.4 compatible), PNG or JPG. Transparent PNG designs require the PHP GD extension.']);}$path=$file->store('certificate-templates','local');$values+=['file_path'=>$path,'format'=>$format,'width_mm'=>$width,'height_mm'=>$height];}
        try{DB::table('certificate_templates')->updateOrInsert(['course_id'=>$id],$values+['created_at'=>$template?->created_at??now()]);}catch(\Throwable $e){if($path)Storage::disk('local')->delete($path);throw $e;}
        if($path&&$template)Storage::disk('local')->delete($template->file_path);
        return redirect('/admin/certificates/'.$id.'/template')->with('success','Certificate design and field positions saved.');
    }
    public function background(Request $r,string $id) {abort_unless($r->user()->role==='admin',403);$this->course($r,$id);$template=DB::table('certificate_templates')->where('course_id',$id)->first();abort_unless($template&&Storage::disk('local')->exists($template->file_path),404);return response()->file(Storage::disk('local')->path($template->file_path),['Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff']);}
    public function preview(Request $r,string $id) {abort_unless($r->user()->role==='admin',403);$course=$this->course($r,$id);$template=DB::table('certificate_templates')->where('course_id',$id)->first();abort_unless($template,404);$sample=(object)['user_id'=>$r->user()->id,'course_id'=>$id,'recommended_by'=>$course->instructor_id,'recommended_at'=>now(),'reference'=>'PREVIEW-NOT-ISSUED'];return response(app(CertificatePdf::class)->render($sample,$template),200,['Content-Type'=>'application/pdf','Content-Disposition'=>'inline; filename="certificate-preview.pdf"','Cache-Control'=>'private, no-store']);}
    public function recommend(Request $r,string $id) {
        $course=$this->course($r,$id);abort_unless($r->user()->role==='instructor'&&$course->instructor_id===$r->user()->id,403,'Only the assigned instructor can recommend completion.');$data=$r->validate(['user_id'=>'required|integer|exists:users,id']);$user=User::findOrFail($data['user_id']);
        DB::transaction(function()use($user,$id,$r){DB::table('enrolments')->where('user_id',$user->id)->where('course_id',$id)->lockForUpdate()->first();LearningAccess::requireCourse($user,(int)$id);abort_unless(LearningAccess::completionReady($user,(int)$id),422,'All published lessons and practical assessments must be completed first.');
            DB::table('course_certificates')->insertOrIgnore(['user_id'=>$user->id,'course_id'=>$id,'recommended_by'=>$r->user()->id,'recommended_at'=>now(),'reference'=>'LW-'.$id.'-'.$user->id.'-'.strtoupper(bin2hex(random_bytes(4))),'created_at'=>now(),'updated_at'=>now()]);Workflow::insert('audit_logs',['user_id'=>$r->user()->id,'module'=>'certificates','action'=>'recommend','record_id'=>$user->id]);});
        return back()->with('success','Completion recommended. The learner can view and download the certificate.');
    }
    public function download(Request $r,string $id) {
        abort_unless($r->user()->role==='participant',403);LearningAccess::requireCourse($r->user(),(int)$id);abort_unless(LearningAccess::completionReady($r->user(),(int)$id),422,'Finish the course first.');
        $certificate=DB::table('course_certificates')->where('user_id',$r->user()->id)->where('course_id',$id)->first();abort_unless($certificate,403,'Awaiting the assigned instructor recommendation.');
        if(!$certificate->file_path){$template=DB::table('certificate_templates')->where('course_id',$id)->first();$bytes=app(CertificatePdf::class)->render($certificate,$template);$path='certificates/'.$certificate->reference.'.pdf';Storage::disk('local')->put($path,$bytes);DB::table('course_certificates')->where('id',$certificate->id)->update(['file_path'=>$path,'updated_at'=>now()]);$certificate->file_path=$path;}
        abort_unless(Storage::disk('local')->exists($certificate->file_path),404);
        return response()->file(Storage::disk('local')->path($certificate->file_path),['Content-Type'=>'application/pdf','Content-Disposition'=>($r->boolean('download')?'attachment':'inline').'; filename="'.$certificate->reference.'.pdf"','Cache-Control'=>'private, no-store']);
    }
}
