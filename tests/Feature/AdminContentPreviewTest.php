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

    public function test_staff_view_course_as_learner_in_each_language(): void
    {
        $course=$this->course();
        $module=Workflow::insert('course_modules',['course_id'=>$course,'title'=>'Getting started','title_lg'=>'Okutandika','position'=>1,'status'=>'published']);
        Workflow::insert('lessons',['course_id'=>$course,'module_id'=>$module,'title'=>'Second English','body'=>'Second body','content_language'=>'en','position'=>2,'status'=>'published']);
        Workflow::insert('lessons',['course_id'=>$course,'module_id'=>$module,'title'=>'First English','body'=>'First body','content_language'=>'en','position'=>1,'status'=>'published']);
        Workflow::insert('lessons',['course_id'=>$course,'title'=>'Unfinished','body'=>'Draft body','content_language'=>'en','position'=>3,'status'=>'draft']);
        Workflow::insert('lessons',['course_id'=>$course,'module_id'=>$module,'title'=>null,'body'=>null,'title_lg'=>'Essomo erisooka','body_lg'=>'Ebirimu','content_language'=>'lg','position'=>1,'status'=>'published']);
        Workflow::insert('assignments',['course_id'=>$course,'title'=>'English task','instructions'=>'Describe your coop','pass_mark'=>60,'content_language'=>'en','status'=>'published']);
        Workflow::insert('resources',['course_id'=>$course,'title'=>'English guide','description'=>'Read this','content_format'=>'text','content_source'=>'write','content_body'=>'Guide text','content_language'=>'en','status'=>'published']);

        $admin=$this->user('admin');
        $this->actingAs($admin)->get('/admin/courses')->assertOk()->assertSee('href="/admin/courses/'.$course.'/learner-view"',false);

        $english=$this->get("/admin/courses/$course/learner-view")->assertOk()
            ->assertSee('Getting started')->assertSee('Second body')->assertSee('Describe your coop')->assertSee('Pass mark 60%')->assertSee('Guide text')
            ->assertSee('Not shown because they are drafts: 1 lesson in English')
            ->assertDontSee('Draft body')->assertDontSee('Essomo erisooka');
        $this->assertLessThan(strpos($english->getContent(),'Second English'),strpos($english->getContent(),'First English'));

        $this->get("/admin/courses/$course/learner-view?language=lg")->assertOk()
            ->assertSee('Okutandika')->assertSee('Essomo erisooka')->assertSee('Ebirimu')
            ->assertDontSee('First English')->assertDontSee('Guide text')
            ->assertSee('Assignments exist in English only, so Luganda learners cannot complete this course yet.');
    }

    public function test_learner_view_is_limited_to_staff_who_can_edit_the_course(): void
    {
        $course=$this->course();
        $this->actingAs($this->user('participant'))->get("/admin/courses/$course/learner-view")->assertForbidden();
        $this->actingAs($this->user('instructor'))->get("/admin/courses/$course/learner-view")->assertNotFound();
        $this->actingAs($this->user('manager'))->get("/admin/courses/$course/learner-view")->assertOk();
        $this->get("/admin/courses/$course/learner-view?language=fr")->assertSessionHasErrors('language');
    }

    public function test_other_modules_have_no_preview_button(): void
    {
        $this->course();
        $this->actingAs($this->user('admin'))->get('/admin/courses')->assertOk()->assertDontSee('preview-record-',false);
    }
}
