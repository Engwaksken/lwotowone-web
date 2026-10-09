<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\{AiAssistant, MentorMatcher, Workflow};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Crypt, DB, Http, Storage};
use Tests\TestCase;

class AiLearningMediaTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $role, array $extra=[]): User
    {
        return User::create(array_merge([
            'name'=>ucfirst($role),'email'=>uniqid().'@example.test','password'=>'A-long-test-password',
            'role'=>$role,'status'=>'active','profile_complete'=>$role==='participant','learning_access_paid'=>$role==='participant',
        ],$extra));
    }

    private function course(User $instructor): int
    {
        $program=Workflow::insert('programs',['title'=>'Practical skills','slug'=>uniqid(),'category'=>'TVET','summary'=>'Skills','body'=>'Skills','status'=>'published']);
        return Workflow::insert('courses',['title'=>'Agribusiness basics','program_id'=>$program,'instructor_id'=>$instructor->id,'summary'=>'Learn practical farming skills','level'=>'Beginner','duration_hours'=>4,'status'=>'published']);
    }

    private function configureAi(string $key='test-secret'): void
    {
        foreach(['ai_provider'=>'openai-compatible','ai_api_base_url'=>'https://api.example.test/v1','ai_api_model'=>'sample-model','ai_api_key'=>'enc:'.Crypt::encryptString($key)] as $name=>$value){
            DB::table('settings')->insert(['key'=>$name,'value'=>$value,'created_at'=>now(),'updated_at'=>now()]);
        }
    }

    public function test_admin_can_save_encrypted_ai_api_settings_and_settings_page_never_reveals_key(): void
    {
        $admin=$this->account('admin');
        $this->actingAs($admin)->put('/admin/site-settings/ai',[
            'ai_provider'=>'openai-compatible','ai_api_base_url'=>'https://api.example.test/v1','ai_api_model'=>'sample-model','ai_api_key'=>'super-secret-token',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $stored=DB::table('settings')->where('key','ai_api_key')->value('value');
        $this->assertStringStartsWith('enc:',$stored);
        $this->assertSame('super-secret-token',Crypt::decryptString(substr($stored,4)));
        $this->get('/admin/site-settings#settings-panel-ai')->assertOk()->assertSee('AI API settings')->assertDontSee('super-secret-token');
        $this->assertTrue(app(AiAssistant::class)->configured());
    }

    public function test_chat_uses_configured_ai_and_has_safe_builtin_fallback(): void
    {
        Http::fake(['api.example.test/*'=>Http::response(['choices'=>[['message'=>['content'=>'Here are some course options.']]]])]);
        $this->configureAi();
        $this->postJson('/help/chat',['message'=>'Can you help me find a course?'])->assertOk()->assertJsonPath('reply','Here are some course options.')->assertJsonPath('ai',true);
        Http::assertSent(fn($request)=>$request->url()==='https://api.example.test/v1/chat/completions'&&$request->hasHeader('Authorization','Bearer test-secret')&&$request['model']==='sample-model');

        DB::table('settings')->where('key','ai_api_key')->delete();
        $this->postJson('/help/chat',['message'=>'How do I find a course?'])->assertOk()->assertJsonPath('ai',false)->assertJsonPath('reply','Browse courses at /explore/courses or programmes at /explore/programs. Open an item to read the details and next steps.');
    }

    public function test_mentor_matching_uses_ai_recommendations_and_only_returns_known_mentor_ids(): void
    {
        $this->configureAi();
        $learner=$this->account('participant',['transformation_objective'=>'I want to learn poultry farming']);
        $first=(object)['id'=>51,'name'=>'Mentor One','expertise'=>'Marketing','bio'=>'Business promotion'];
        $recommended=(object)['id'=>52,'name'=>'Mentor Two','expertise'=>'Poultry farming','bio'=>'Farm management'];
        Http::fake(['api.example.test/*'=>Http::response(['choices'=>[['message'=>['content'=>json_encode(['matches'=>[['id'=>52,'reason'=>'Their farming background fits your interests.'],['id'=>999,'reason'=>'Unknown']]])]]]])]);

        $matches=app(MentorMatcher::class)->rank($learner,collect([$first,$recommended]));

        $this->assertSame([52,51],$matches->pluck('id')->all());
        $this->assertSame('Their farming background fits your interests.',$matches->first()->match_reason);
    }

    public function test_course_video_and_resource_files_render_in_platform_without_download_actions(): void
    {
        Storage::fake('local');
        $learner=$this->account('participant');$course=$this->course($this->account('instructor'));
        Workflow::run($learner,'enrol',['course_id'=>$course]);
        Workflow::insert('lessons',['course_id'=>$course,'title'=>'Poultry lesson','body'=>'Lesson text','video_url'=>'https://youtu.be/abcdefghijk','position'=>1,'status'=>'published']);
        $resource=Workflow::insert('resources',['course_id'=>$course,'title'=>'Farm walkthrough','description'=>'Watch the farm walkthrough','file_path'=>'resources/farm-walkthrough.mp4','status'=>'published']);
        Storage::disk('local')->put('resources/farm-walkthrough.mp4','sample video bytes');

        $page=$this->actingAs($learner)->get('/learning/'.$course)->assertOk()->assertSee('youtube-nocookie.com/embed/abcdefghijk')->assertSee('controlslist="nodownload noplaybackrate"',false)->assertDontSee('Download resource')->assertSee('learning/content/resources/'.$resource);
        $stream=$this->get('/learning/content/resources/'.$resource)->assertOk();
        $this->assertStringContainsString('inline',(string)$stream->headers->get('Content-Disposition'));
        $legacy=$this->get('/files/resources/'.$resource)->assertOk();
        $this->assertStringContainsString('inline',(string)$legacy->headers->get('Content-Disposition'));
    }
}
