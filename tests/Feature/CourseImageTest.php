<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Workflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CourseImageTest extends TestCase
{
    use RefreshDatabase;

    private array $saved = [];

    protected function tearDown(): void
    {
        foreach ($this->saved as $path) @unlink(public_path(ltrim($path, '/')));
        parent::tearDown();
    }

    private function user(string $role): User
    {
        return User::create(['name'=>ucfirst($role),'email'=>uniqid().'@example.test','password'=>'Long-test-password','role'=>$role,'status'=>'active']);
    }

    private function course(): array
    {
        $program=Workflow::insert('programs',['title'=>'Program','slug'=>uniqid(),'category'=>'TVET','summary'=>'Text','body'=>'Text','status'=>'published']);
        return ['title'=>'Tailoring basics','program_id'=>$program,'summary'=>'Learn to sew','level'=>'Beginner','duration_hours'=>4,'status'=>'published'];
    }

    private function path(): ?string
    {
        $path = DB::table('courses')->value('image_path');
        if ($path) $this->saved[] = $path;
        return $path;
    }

    public function test_admin_uploads_replaces_and_removes_a_course_image_that_shows_on_course_pages(): void
    {
        $admin=$this->user('admin');$fields=$this->course();
        $this->actingAs($admin)->get('/admin/courses/create')->assertOk()->assertSee('name="image"',false);
        $this->post('/admin/courses',$fields+['image'=>UploadedFile::fake()->image('cover.jpg',1600,900)])->assertRedirect('/admin/courses')->assertSessionHasNoErrors();

        $first=$this->path();
        $this->assertMatchesRegularExpression('#^/course-images/course-[0-9a-f]{16}\.jpg$#',$first);
        $this->assertFileExists(public_path(ltrim($first,'/')));
        $id=DB::table('courses')->value('id');

        $this->get('/courses')->assertSee('src="'.$first.'"',false);
        $this->get('/courses/'.$id)->assertSee('src="'.$first.'"',false);
        $this->get('/')->assertSee('src="'.$first.'"',false);
        $this->get("/admin/courses/$id/edit")->assertSee('src="'.$first.'"',false)->assertSee('Remove this image');

        // Saving other changes keeps the image; a new upload replaces it and removes the old file.
        $this->put("/admin/courses/$id",$fields+['title'=>'Tailoring'])->assertSessionHasNoErrors();
        $this->assertSame($first,$this->path());
        $this->put("/admin/courses/$id",$fields+['image'=>UploadedFile::fake()->image('new.png',1200,675)])->assertSessionHasNoErrors();
        $second=$this->path();
        $this->assertNotSame($first,$second);
        $this->assertFileDoesNotExist(public_path(ltrim($first,'/')));

        $this->put("/admin/courses/$id",$fields+['remove_image'=>1])->assertSessionHasNoErrors();
        $this->assertNull(DB::table('courses')->value('image_path'));
        $this->assertFileDoesNotExist(public_path(ltrim($second,'/')));
    }

    public function test_course_image_must_be_a_small_picture(): void
    {
        $this->actingAs($this->user('admin'));
        $this->post('/admin/courses',$this->course()+['image'=>UploadedFile::fake()->create('notes.pdf',10,'application/pdf')])->assertSessionHasErrors('image');
        $this->post('/admin/courses',$this->course()+['image'=>UploadedFile::fake()->image('huge.jpg')->size(5000)])->assertSessionHasErrors('image');
        $this->assertDatabaseCount('courses',0);
    }

    public function test_learners_and_the_app_see_the_course_image(): void
    {
        $this->actingAs($this->user('admin'))->post('/admin/courses',$this->course()+['image'=>UploadedFile::fake()->image('cover.webp',800,450)])->assertSessionHasNoErrors();
        $path=$this->path();
        $learner=User::create(['name'=>'Learner','email'=>uniqid().'@example.test','password'=>'Long-test-password','role'=>'participant','status'=>'active','profile_complete'=>true,'learning_access_paid'=>true]);
        $this->actingAs($learner)->get('/portal/learn')->assertOk()->assertSee('src="'.$path.'"',false);
        $this->getJson('/api/snapshot')->assertOk()->assertJsonPath('courses.0.image_url',url($path));
    }
}
