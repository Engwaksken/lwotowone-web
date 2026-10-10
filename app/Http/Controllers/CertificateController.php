<?php
namespace App\Http\Controllers;

use App\Models\User;
use App\Services\{CertificatePdf,LearningAccess,Workflow};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Storage};
use Illuminate\Validation\ValidationException;

class CertificateController extends Controller
{
    private function course(Request $r,string $id): object {$course=\App\Services\Catalog::query('courses')->findOrFail($id);abort_unless((new \App\Policies\CoursePolicy)->view($r->user(),$course),403);return $course;}
    public function index(Request $r) {
        abort_unless($r->user()->manager()||$r->user()->role==='instructor',403);$courses=\App\Services\Catalog::query('courses');if(!$r->user()->manager())$courses->assignedToInstructor((int)$r->user()->id);
        $courses=$courses->get();$learners=DB::table('enrolments')->join('users','users.id','=','enrolments.user_id')->whereIn('enrolments.course_id',$courses->pluck('id'))->select('enrolments.course_id','users.id','users.name')->get();
        $templates=DB::table('certificate_templates')->leftJoin('courses','courses.id','=','certificate_templates.course_id')->leftJoin('events','events.id','=','certificate_templates.event_id');
        if(!$r->user()->manager())$templates->whereIn('certificate_templates.course_id',$courses->pluck('id'));
        $templates=$templates->select('certificate_templates.*','courses.title as course_name','events.title as event_name')->orderByDesc('certificate_templates.updated_at')->orderByDesc('certificate_templates.id')->paginate(20)->withQueryString();
        $events=$r->user()->manager()?DB::table('events')->orderBy('title')->get(['id','title']):collect();
        $configuredCourses=DB::table('certificate_templates')->whereNotNull('course_id')->pluck('course_id');$configuredEvents=DB::table('certificate_templates')->whereNotNull('event_id')->pluck('event_id');
        return view('admin.certificates',['courses'=>$courses,'events'=>$events,'templates'=>$templates,'configuredCourses'=>$configuredCourses,'configuredEvents'=>$configuredEvents,'learners'=>$learners,'certificates'=>DB::table('course_certificates')->whereIn('course_id',$courses->pluck('id'))->get()]);
    }
    public function create(Request $r)
    {
        abort_unless($r->user()->role === 'admin', 403);
        $data = $r->validate([
            'name' => 'required|string|max:255', 'subject_type' => 'required|in:course,event',
            'course_id' => ['nullable', 'required_if:subject_type,course', 'prohibited_unless:subject_type,course', 'integer', 'exists:courses,id', \Illuminate\Validation\Rule::unique('certificate_templates', 'course_id')],
            'event_id' => ['nullable', 'required_if:subject_type,event', 'prohibited_unless:subject_type,event', 'integer', 'exists:events,id', \Illuminate\Validation\Rule::unique('certificate_templates', 'event_id')],
            'template' => $this->fileRules(true),
        ]);
        $type = $data['subject_type']; $id = (string)$data[$type.'_id'];
        $this->subject($r, $type, $id);
        $upload = $this->upload($r);
        try {
            DB::transaction(function () use ($r, $type, $id, $data, $upload) {
                abort_unless(DB::table($type === 'course' ? 'courses' : 'events')->where('id', $id)->lockForUpdate()->first(), 404);
                if (DB::table('certificate_templates')->where($type.'_id', $id)->exists()) {
                    throw ValidationException::withMessages([$type.'_id' => 'A certificate template already exists for this '.$type.'. Edit its design from the table.']);
                }
                $templateId = Workflow::insert('certificate_templates', $upload + ['name' => $data['name'], 'course_id' => $type === 'course' ? $id : null, 'event_id' => $type === 'event' ? $id : null, 'placements' => json_encode(CertificatePdf::defaults(), JSON_THROW_ON_ERROR)]);
                Workflow::insert('audit_logs', ['user_id' => $r->user()->id, 'module' => 'certificate_templates', 'action' => 'create', 'record_id' => $templateId]);
            });
        } catch (\Throwable $e) { Storage::disk('local')->delete($upload['file_path']); throw $e; }
        return redirect($this->designUrl($type, $id).'/template')->with('success', 'Certificate added. Drag fields onto the design and save their positions.');
    }

    private function subject(Request $r, string $type, string $id): object
    {
        abort_unless($r->user()->role === 'admin', 403);
        $subject = DB::table($type === 'course' ? 'courses' : 'events')->find($id);
        abort_unless($subject, 404);
        return $subject;
    }

    private function designUrl(string $type, string $id): string
    {
        return '/admin/certificates/'.($type === 'event' ? 'events/' : '').$id;
    }

    public function editor(Request $r, string $id) { return $this->designEditor($r, 'course', $id); }
    public function eventEditor(Request $r, string $id) { return $this->designEditor($r, 'event', $id); }

    private function designEditor(Request $r, string $type, string $id)
    {
        $subject = $this->subject($r, $type, $id);
        $template = DB::table('certificate_templates')->where($type.'_id', $id)->first();
        $placements = CertificatePdf::defaults();
        foreach ($template ? json_decode($template->placements, true) : [] as $key => $place) {
            if (isset($placements[$key])) $placements[$key] = array_replace($placements[$key], $place);
        }
        return view('admin.certificate-template', ['subject' => $subject, 'subjectType' => $type, 'designUrl' => $this->designUrl($type, $id), 'template' => $template, 'placements' => $placements, 'fields' => CertificatePdf::fields($type)]);
    }

    private function fileRules(bool $required): array
    {
        return [$required ? 'required' : 'nullable', 'file', 'mimes:pdf,png,jpg,jpeg', 'extensions:pdf,png,jpg,jpeg', 'max:10240'];
    }

    private function upload(Request $r): array
    {
        $file = $r->file('template'); $format = strtolower($file->getClientOriginalExtension());
        if ($format === 'jpeg') $format = 'jpg';
        try { [$width, $height] = app(CertificatePdf::class)->size($file->getRealPath(), $format); }
        catch (\Throwable) { throw ValidationException::withMessages(['template' => 'Cannot render this design. Use a single-page, unencrypted PDF (PDF 1.4 compatible), PNG or JPG. Transparent PNG designs require the PHP GD extension.']); }
        return ['file_path' => $file->store('certificate-templates', 'local'), 'format' => $format, 'width_mm' => $width, 'height_mm' => $height];
    }

    public function save(Request $r, string $id) { return $this->saveDesign($r, 'course', $id); }
    public function saveEvent(Request $r, string $id) { return $this->saveDesign($r, 'event', $id); }

    private function saveDesign(Request $r, string $type, string $id)
    {
        $subject = $this->subject($r, $type, $id);
        $template = DB::table('certificate_templates')->where($type.'_id', $id)->first();
        $rules = ['name' => 'sometimes|required|string|max:255', 'template' => $this->fileRules(!$template), 'placements' => 'required|array'];
        foreach (CertificatePdf::fields($type) as $field => $label) {
            $prefix = 'placements.'.$field; $rules[$prefix] = 'required|array'; $rules[$prefix.'.enabled'] = 'nullable|boolean';
            foreach (['x', 'y'] as $key) $rules[$prefix.'.'.$key] = 'required|numeric|min:0|max:95';
            $rules[$prefix.'.width'] = 'required|numeric|min:5|max:100'; $rules[$prefix.'.font_size'] = 'required|numeric|min:8|max:48'; $rules[$prefix.'.align'] = 'required|in:L,C,R';
            $rules[$prefix.'.color'] = ['sometimes', 'required', 'regex:/^#[0-9a-fA-F]{6}$/'];
            $rules[$prefix.'.font_family'] = ['sometimes', 'required', \Illuminate\Validation\Rule::in(array_keys(CertificatePdf::FONTS))];
            $rules[$prefix.'.font_style'] = ['nullable', \Illuminate\Validation\Rule::in(array_keys(CertificatePdf::STYLES))];
        }
        $data = $r->validate($rules); $placements = [];
        foreach (CertificatePdf::fields($type) as $field => $label) {
            $place = array_replace(CertificatePdf::defaults()[$field], $data['placements'][$field]);
            if ($place['x'] + $place['width'] > 100) throw ValidationException::withMessages(['placements.'.$field.'.width' => 'Field width must fit within the template.']);
            $place['enabled'] = $r->boolean('placements.'.$field.'.enabled'); $place['font_style'] = $place['font_style'] ?? '';
            $placements[$field] = array_intersect_key($place, array_flip(['x', 'y', 'width', 'font_size', 'align', 'enabled', 'color', 'font_family', 'font_style']));
        }
        $upload = $r->hasFile('template') ? $this->upload($r) : []; $oldPath = null;
        try {
            $oldPath = DB::transaction(function () use ($r, $type, $id, $subject, $data, $placements, $upload) {
                abort_unless(DB::table($type === 'course' ? 'courses' : 'events')->where('id', $id)->lockForUpdate()->first(), 404);
                $previous = DB::table('certificate_templates')->where($type.'_id', $id)->lockForUpdate()->first();
                $values = $upload + ['name' => $data['name'] ?? $previous?->name ?? mb_substr($subject->title.' certificate', 0, 255), 'placements' => json_encode($placements, JSON_THROW_ON_ERROR), 'updated_at' => now()];
                DB::table('certificate_templates')->updateOrInsert([$type.'_id' => $id], $values + ['created_at' => $previous?->created_at ?? now()]);
                Workflow::insert('audit_logs', ['user_id' => $r->user()->id, 'module' => 'certificate_templates', 'action' => $previous ? 'update' : 'create', 'record_id' => DB::table('certificate_templates')->where($type.'_id', $id)->value('id')]);
                return $previous?->file_path;
            });
        } catch (\Throwable $e) { if ($upload) Storage::disk('local')->delete($upload['file_path']); throw $e; }
        if ($upload && $oldPath) Storage::disk('local')->delete($oldPath);
        return redirect($this->designUrl($type, $id).'/template')->with('success', 'Certificate design and field positions saved.');
    }

    public function background(Request $r, string $id) { return $this->designBackground($r, 'course', $id); }
    public function eventBackground(Request $r, string $id) { return $this->designBackground($r, 'event', $id); }

    private function designBackground(Request $r, string $type, string $id)
    {
        $this->subject($r, $type, $id); $template = DB::table('certificate_templates')->where($type.'_id', $id)->first();
        abort_unless($template && Storage::disk('local')->exists($template->file_path), 404);
        return response()->file(Storage::disk('local')->path($template->file_path), ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'])->setPrivate();
    }

    public function preview(Request $r, string $id) { return $this->designPreview($r, 'course', $id); }
    public function eventPreview(Request $r, string $id) { return $this->designPreview($r, 'event', $id); }

    private function designPreview(Request $r, string $type, string $id)
    {
        $subject = $this->subject($r, $type, $id); $template = DB::table('certificate_templates')->where($type.'_id', $id)->first(); abort_unless($template, 404);
        $sample = (object)['user_id' => $r->user()->id, $type.'_id' => $id, 'recommended_by' => $type === 'course' ? $subject->instructor_id : $r->user()->id, 'recommended_at' => now(), 'reference' => 'PREVIEW-NOT-ISSUED'];
        $pdf = app(CertificatePdf::class); $bytes = $type === 'event' ? $pdf->renderEvent($sample, $template) : $pdf->render($sample, $template);
        return response($bytes, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="certificate-preview.pdf"', 'Cache-Control' => 'private, no-store']);
    }
    public function recommend(Request $r,string $id) {
        $course=$this->course($r,$id);abort_unless((new \App\Policies\CoursePolicy)->recommendCertificate($r->user(),$course),403,'Only an assigned instructor can recommend completion.');$data=$r->validate(['user_id'=>'required|integer|exists:users,id']);$user=User::findOrFail($data['user_id']);
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
