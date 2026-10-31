<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Workflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminContentPreviewTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::create(['name'=>ucfirst($role),'email'=>uniqid().'@example.test','password'=>'Long-test-password','role'=>$role,'status'=>'active']);
    }

    private function course(): int
    {
        $program=Workflow::insert('programs',['title'=>'Program','slug'=>uniqid(),'category'=>'TVET','summary'=>'Text','body'=>'Text','status'=>'published']);
        return Workflow::insert('courses',['program_id'=>$program,'title'=>'Course','summary'=>'Text','instructor_id'=>$this->user('instructor')->id,'duration_hours'=>1,'status'=>'published']);
    }

    public function test_lessons_list_previews_written_text_in_each_language_and_uploaded_media(): void
    {
        Storage::fake('local');
        $course=$this->course();
        $english=Workflow::insert('lessons',['course_id'=>$course,'title'=>'Feeding chicks','body'=>'Give clean water every morning.','content_format'=>'text','content_source'=>'write','content_body'=>'Give clean water every morning.','content_language'=>'en','position'=>1,'status'=>'published']);
        $luganda=Workflow::insert('lessons',['course_id'=>$course,'title'=>null,'body'=>null,'title_lg'=>'Okuliisa obukoko','body_lg'=>'Bawe amazzi amayonjo buli nkya.','content_format'=>'text','content_source'=>'write','content_body_lg'=>'Bawe amazzi amayonjo buli nkya.','content_language'=>'lg','position'=>2,'status'=>'draft']);
        Storage::disk('local')->put('lessons/clip.mp4','video-bytes');
        $video=Workflow::insert('lessons',['course_id'=>$course,'title'=>'Building a coop','body'=>'','content_format'=>'video','content_source'=>'upload','file_path'=>'lessons/clip.mp4','content_language'=>'en','position'=>3,'status'=>'published']);

        $admin=$this->user('admin');
        $this->actingAs($admin)->get('/admin/lessons')->assertOk()
            ->assertSee('data-dialog-open="preview-record-'.$english.'"',false)
            ->assertSee('Preview: Feeding chicks')->assertSee('Give clean water every morning.')
            ->assertSee('Preview: Okuliisa obukoko')->assertSee('Bawe amazzi amayonjo buli nkya.')->assertSee('lang="lg"',false)
            ->assertSee('Draft: learners cannot see this yet')
            ->assertSee('Preview: Building a coop')->assertSee('/learning/content/lessons/'.$video,false)->assertSee('preload="none"',false);

        $this->get('/learning/content/lessons/'.$video)->assertOk();
    }

    public function test_resources_list_previews_written_text_and_reports_missing_content(): void
    {
        $course=$this->course();
        $written=Workflow::insert('resources',['course_id'=>$course,'title'=>'Vaccination guide','description'=>'When to vaccinate','content_format'=>'text','content_source'=>'write','content_body'=>'Vaccinate at day 7.','content_language'=>'en','status'=>'published']);
        $empty=Workflow::insert('resources',['course_id'=>$course,'title'=>'Empty resource','description'=>'Nothing yet','content_language'=>'en','status'=>'published']);

        $this->actingAs($this->user('admin'))->get('/admin/resources')->assertOk()
            ->assertSee('data-dialog-open="preview-record-'.$written.'"',false)
            ->assertSee('When to vaccinate')->assertSee('Vaccinate at day 7.')
            ->assertSee('data-dialog-open="preview-record-'.$empty.'"',false)->assertSee('No content has been added yet.');
    }

    public function test_other_modules_have_no_preview_button(): void
    {
        $this->course();
        $this->actingAs($this->user('admin'))->get('/admin/courses')->assertOk()->assertDontSee('preview-record-',false);
    }
}
