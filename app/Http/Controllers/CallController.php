<?php
namespace App\Http\Controllers;

use App\Services\{LearningAccess,Workflow};
use BaconQrCode\{Writer,Renderer\ImageRenderer,Renderer\Image\SvgImageBackEnd,Renderer\RendererStyle\RendererStyle};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CallController extends Controller
{
    private function manager(Request $r): void {abort_unless($r->user()->manager(),403);}
    public function index(Request $r) {
        $this->manager($r);$filters=$r->validate(['status'=>'nullable|in:all,draft,published,closed']);$status=$filters['status']??'all';
        $calls=DB::table('application_calls');if($status!=='all')$calls->where('status',$status);
        return view('admin.calls',['calls'=>$calls->latest()->paginate(15)->withQueryString(),'status'=>$status,'courses'=>DB::table('courses')->where('status','published')->get(),'opportunities'=>DB::table('opportunities')->where('status','published')->get(),
            'applications'=>DB::table('call_applications')->join('users','users.id','=','call_applications.user_id')->join('application_calls','application_calls.id','=','call_applications.call_id')->select('call_applications.*','users.name','users.email','application_calls.title')->latest('call_applications.id')->paginate(20,['*'],'applications_page')->withQueryString()]);
    }
    public function save(Request $r,?string $id=null) {
        $this->manager($r);$data=$r->validate(['title'=>'required|string|max:255','description'=>'required|string|max:10000','course_id'=>'nullable|integer|exists:courses,id','opportunity_id'=>'nullable|integer|exists:opportunities,id','opens_at'=>'required|date','closes_at'=>'required|date|after:opens_at','status'=>'required|in:draft,published,closed']);
        abort_unless(!empty($data['course_id'])||!empty($data['opportunity_id']),422,'Select a course or opportunity.');
        foreach(['course_id'=>'courses','opportunity_id'=>'opportunities'] as $key=>$table)if(!empty($data[$key]))abort_unless(DB::table($table)->where('id',$data[$key])->where('status','published')->exists(),422,'Select a published course or opportunity.');
        if($id){$call=DB::table('application_calls')->find($id);abort_unless($call,404);abort_if(DB::table('call_applications')->where('call_id',$id)->exists()&&($call->course_id!=($data['course_id']??null)||$call->opportunity_id!=($data['opportunity_id']??null)),422,'Calls with applications cannot change their course/opportunity.');DB::table('application_calls')->where('id',$id)->update($data+['updated_at'=>now()]);}
        else DB::table('application_calls')->insert($data+['token'=>(string)Str::uuid(),'created_by'=>$r->user()->id,'created_at'=>now(),'updated_at'=>now()]);
        return back()->with('success','Application call saved.');
    }
    public function listing() {return view('public.calls',['calls'=>DB::table('application_calls')->where('status','published')->where('opens_at','<=',now())->where('closes_at','>',now())->latest()->get()]);}
    public function show(Request $r,string $token) {
        $call=DB::table('application_calls')->where('token',$token)->where('status','published')->first();abort_unless($call,404);
        return view('public.call',['call'=>$call,'application'=>$r->user()?DB::table('call_applications')->where('call_id',$call->id)->where('user_id',$r->user()->id)->first():null]);
    }
    public function qr(Request $r,string $id) {
        $this->manager($r);$call=DB::table('application_calls')->find($id);abort_unless($call,404);
        $svg=(new Writer(new ImageRenderer(new RendererStyle(280),new SvgImageBackEnd())))->writeString(url('/calls/'.$call->token));
        return response($svg,200,['Content-Type'=>'image/svg+xml','Content-Disposition'=>'inline; filename="call-'.$id.'-qr.svg"']);
    }
    public function apply(Request $r,string $token) {
        abort_unless($r->user()->role==='participant',403);
        if(!$r->user()->profile_complete)return redirect('/profile')->with('success','Complete your profile before applying.');
        $data=$r->validate(['motivation'=>'required|string|max:10000']);
        DB::transaction(function()use($r,$token,$data){$call=DB::table('application_calls')->where('token',$token)->lockForUpdate()->first();abort_unless($call&&$call->status==='published'&&now()->gte($call->opens_at)&&now()->lt($call->closes_at),422,'This call is not open.');
            abort_if(DB::table('call_applications')->where('call_id',$call->id)->where('user_id',$r->user()->id)->exists(),422,'You have already applied.');
            Workflow::insert('call_applications',$data+['user_id'=>$r->user()->id,'call_id'=>$call->id,'status'=>'submitted']);});
        return redirect('/calls/'.$token)->with('success','Application submitted for M&E review.');
    }
    public function review(Request $r,string $id) {
        $this->manager($r);$data=$r->validate(['status'=>'required|in:approved,rejected','feedback'=>'nullable|string|max:5000']);
        DB::transaction(function()use($r,$id,$data){$application=DB::table('call_applications')->where('id',$id)->lockForUpdate()->first();abort_unless($application,404);if($application->status==='approved')return;
            $call=DB::table('application_calls')->find($application->call_id);$user=DB::table('users')->find($application->user_id);abort_unless($user&&$user->role==='participant'&&$user->status==='active'&&$user->profile_complete,422,'The learner must be active and have a complete profile.');
            if($data['status']==='approved'){
                if($call->course_id){$course=Workflow::published('courses',$call->course_id);if($course->prerequisite_course_id)abort_unless(LearningAccess::completionReady(\App\Models\User::find($user->id),(int)$course->prerequisite_course_id),422,'The learner must finish the prerequisite course before enrollment approval.');LearningAccess::trial($user->id,$call->course_id);}
                if($call->opportunity_id){Workflow::published('opportunities',$call->opportunity_id);DB::table('applications')->updateOrInsert(['user_id'=>$user->id,'opportunity_id'=>$call->opportunity_id],['motivation'=>$application->motivation,'status'=>'accepted','feedback'=>$data['feedback']??null,'created_at'=>now(),'updated_at'=>now()]);}
            }
            DB::table('call_applications')->where('id',$id)->update($data+['reviewed_by'=>$r->user()->id,'reviewed_at'=>now(),'updated_at'=>now()]);
            Workflow::insert('audit_logs',['user_id'=>$r->user()->id,'module'=>'call_applications','action'=>$data['status'],'record_id'=>$id]);
        });return back()->with('success','Application reviewed. Approved course applications receive a 12-hour trial starting now.');
    }
}
