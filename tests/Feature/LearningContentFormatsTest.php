<?php

namespace Tests\Feature;

use App\Models\{InstructorAssignments, Record, User};
use App\Services\{LearningAccess, Snapshot, Workflow};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Storage};
use Tests\TestCase;

class LearningContentFormatsTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::create(['name' => 'Test '.$role, 'email' => uniqid().'@example.test', 'password' => 'A-long-test-password', 'role' => $role, 'status' => 'active', 'profile_complete' => true, 'learning_access_paid' => true]);
    }

    private function course(User $teacher): int
    {
        $program = Workflow::insert('programs', ['title' => 'Practical programme', 'slug' => uniqid(), 'category' => 'TVET', 'summary' => 'Text', 'body' => 'Text', 'status' => 'published']);
        $id = Workflow::insert('courses', ['program_id' => $program, 'title' => 'Named course', 'summary' => 'Text', 'duration_hours' => 2, 'status' => 'published']);
        InstructorAssignments::sync(Record::for('courses')->newQuery()->findOrFail($id), [$teacher->id]);
        return $id;
    }

    private function payload(int $course, string $module): array
    {
        return ['course_id' => $course, 'title' => 'Learning item', 'status' => 'published'] + ($module === 'lessons' ? ['position' => 1] : ['description' => 'Resource summary']);
    }

    public function test_forms_and_detail_dialogs_display_relationship_names_and_contextual_columns(): void
    {
        $admin = $this->user('admin'); $teacher = $this->user('instructor'); $course = $this->course($teacher);
        Workflow::insert('lessons', ['course_id' => $course, 'title' => 'Text lesson', 'body' => 'Text', 'position' => 1, 'status' => 'published']);
        $this->actingAs($admin)->get('/admin/courses')->assertOk()->assertSee('Program Name')->assertSee('Instructor Name')->assertSee('Practical programme')->assertSee($teacher->name)->assertDontSee('Program Id')->assertDontSee('Instructor Id');
        $this->get('/admin/lessons')->assertOk()->assertSee('Course Name')->assertSee('Named course')->assertSee('column-primary', false)->assertDontSee('Course Id');
        foreach (['lessons', 'resources'] as $module) {
            $form=$this->get('/admin/'.$module.'/create')->assertOk()->assertSee('Course Name')->assertSee('Content format and source')->assertSee('Audio')->assertSee('Use a URL')->assertDontSee('Course Id');
            if($module==='lessons')$form->assertSee('Lesson content language')->assertSee('Luganda');
        }
    }

    public function test_lessons_and_resources_accept_written_text_urls_and_format_specific_uploads(): void
    {
        Storage::fake('local'); $teacher = $this->user('instructor'); $course = $this->course($teacher);
        $this->actingAs($teacher);
        foreach (['lessons', 'resources'] as $module) {
            $base = $this->payload($course, $module);
            $this->post('/admin/'.$module, $base + ['content_format' => 'text', 'content_source' => 'write', 'content_body' => 'Written learning content'])->assertRedirect()->assertSessionHasNoErrors();
            $this->assertDatabaseHas($module, ['content_format' => 'text', 'content_source' => 'write', 'content_body' => 'Written learning content']);
            foreach (['text' => 'notes.txt', 'file' => 'guide.pdf', 'video' => 'lesson.mp4', 'audio' => 'lesson.mp3'] as $format => $name) {
                $this->post('/admin/'.$module, $base + ['content_format' => $format, 'content_source' => 'url', 'content_url' => 'https://example.test/'.$name])->assertRedirect()->assertSessionHasNoErrors();
                $this->assertDatabaseHas($module, ['content_format' => $format, 'content_source' => 'url', 'content_url' => 'https://example.test/'.$name]);
                $mime = ['text' => 'text/plain', 'file' => 'application/pdf', 'video' => 'video/mp4', 'audio' => 'audio/mpeg'][$format];
                $this->post('/admin/'.$module, $base + ['content_format' => $format, 'content_source' => 'upload', 'file' => UploadedFile::fake()->create($name, 12, $mime)])->assertRedirect()->assertSessionHasNoErrors();
                $item = DB::table($module)->latest('id')->first();
                $this->assertSame($format, $item->content_format); Storage::disk('local')->assertExists($item->file_path);
            }
        }
    }

    public function test_luganda_lesson_can_be_created_without_english_title_or_text(): void
    {
        $teacher = $this->user('instructor'); $course = $this->course($teacher); $learner = $this->user('participant');
        $payload = $this->payload($course, 'lessons'); unset($payload['title']);
        $this->actingAs($teacher)->post('/admin/lessons', $payload + [
            'lesson_language' => 'lg', 'title_lg' => 'Essomo ly’okulima',
            'content_format' => 'text', 'content_source' => 'write', 'content_body_lg' => 'Ebirimu mu Luganda.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $lesson = DB::table('lessons')->latest('id')->first();
        $this->assertSame('lg', $lesson->content_language);
        $this->assertNull($lesson->title);
        $this->assertSame('Essomo ly’okulima', $lesson->title_lg);
        $this->assertNull($lesson->body);
        $this->assertSame('Ebirimu mu Luganda.', $lesson->body_lg);

        $learner->preferred_language = 'lg'; $learner->save();
        LearningAccess::trial($learner->id, $course);
        $localized = Snapshot::get($learner)['lessons']->firstWhere('id', $lesson->id);
        $this->assertSame('Essomo ly’okulima', $localized->title);
        $this->assertSame('Ebirimu mu Luganda.', $localized->body);
    }

    public function test_wrong_file_type_missing_source_and_unsafe_url_are_rejected_without_writes(): void
    {
        Storage::fake('local'); $teacher = $this->user('instructor'); $course = $this->course($teacher);
        $this->actingAs($teacher);
        foreach (['lessons', 'resources'] as $module) {
            $base = $this->payload($course, $module);
            $this->post('/admin/'.$module, $base + ['content_format' => 'audio', 'content_source' => 'upload', 'file' => UploadedFile::fake()->create('guide.pdf', 12, 'application/pdf')])->assertSessionHasErrors('file');
            $this->post('/admin/'.$module, $base + ['content_format' => 'video', 'content_source' => 'upload'])->assertSessionHasErrors('file');
            $this->post('/admin/'.$module, $base + ['content_format' => 'audio', 'content_source' => 'write', 'content_body' => 'Invalid'])->assertSessionHasErrors('content_source');
            $this->post('/admin/'.$module, $base + ['content_format' => 'text', 'content_source' => 'url', 'content_url' => 'javascript:alert(1)'])->assertSessionHasErrors('content_url');
            $this->assertDatabaseCount($module, 0);
        }
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_phone_videos_upload_as_lessons_through_the_progress_uploader(): void
    {
        Storage::fake('local'); config(['media.ffmpeg' => 'missing-ffmpeg-binary']);
        $teacher = $this->user('instructor'); $course = $this->course($teacher);
        $this->actingAs($teacher)->get('/admin/lessons/create')->assertOk()->assertSee('data-upload-progress', false)->assertSee('data-max-file-bytes="'.\App\Services\UploadLimit::fileKilobytes() * 1024 .'"', false);
        $this->postJson('/admin/lessons', $this->payload($course, 'lessons') + ['content_format' => 'video', 'content_source' => 'upload', 'file' => UploadedFile::fake()->create('phone.mov', 20, 'video/quicktime')])
            ->assertOk()->assertJson(['ok' => true, 'redirect' => '/admin/lessons']);
        $lesson = DB::table('lessons')->where('course_id', $course)->first();
        $this->assertSame('video', $lesson->content_format);
        Storage::disk('local')->assertExists($lesson->file_path);
        $this->get('/admin/lessons/'.$lesson->id.'/edit')->assertOk()->assertSee('Saved video')->assertSee('<source src="'.url('/learning/content/lessons/'.$lesson->id).'">', false)->assertSee('data-content-file-preview', false);
        // The lessons list renders an edit form per row; previews there must not start downloading videos and tie up the browser's connections.
        $this->get('/admin/lessons')->assertOk()->assertSee('preload="none"', false)->assertDontSee('preload="metadata"', false);
        $this->get('/learning/content/lessons/'.$lesson->id)->assertOk();
        $this->postJson('/admin/lessons', $this->payload($course, 'lessons') + ['content_format' => 'video', 'content_source' => 'upload', 'file' => UploadedFile::fake()->create('huge.mp4', \App\Services\UploadLimit::fileKilobytes() + 1, 'video/mp4')])
            ->assertUnprocessable()->assertJsonValidationErrors('file');
    }

    public function test_lesson_module_from_another_course_is_a_field_error_not_a_dead_end(): void
    {
        Storage::fake('local'); config(['media.ffmpeg' => 'missing-ffmpeg-binary']);
        $admin = $this->user('admin'); $teacher = $this->user('instructor');
        $course = $this->course($teacher); $otherCourse = $this->course($teacher);
        $module = Workflow::insert('course_modules', ['course_id' => $course, 'title' => 'Week one', 'position' => 1, 'status' => 'published']);
        $otherModule = Workflow::insert('course_modules', ['course_id' => $otherCourse, 'title' => 'Other week', 'position' => 1, 'status' => 'published']);
        $this->actingAs($admin)->get('/admin/lessons')->assertOk()
            ->assertSee('data-course-filter', false)->assertSee('value="'.$otherModule.'"  data-course="'.$otherCourse.'"', false)
            ->assertSee('class="dialog-form" method="post" enctype="multipart/form-data" action="/admin/lessons" data-upload-progress', false);
        $video = fn () => ['content_format' => 'video', 'content_source' => 'upload', 'file' => UploadedFile::fake()->create('clip.mp4', 20, 'video/mp4')];
        $this->post('/admin/lessons', $this->payload($course, 'lessons') + ['module_id' => $otherModule] + $video())->assertSessionHasErrors('module_id');
        $this->postJson('/admin/lessons', $this->payload($course, 'lessons') + ['module_id' => $otherModule] + $video())->assertUnprocessable()->assertJsonValidationErrors('module_id');
        $this->assertSame(0, DB::table('lessons')->count());
        $this->postJson('/admin/lessons', $this->payload($course, 'lessons') + ['module_id' => $module] + $video())->assertOk()->assertJson(['ok' => true]);
        $this->assertSame($module, (int) DB::table('lessons')->value('module_id'));
    }

    public function test_uploaded_lesson_is_private_sequenced_and_redacted_when_locked(): void
    {
        $this->endFreeAccess();
        Storage::fake('local'); $teacher = $this->user('instructor'); $course = $this->course($teacher); $learner = $this->user('participant');
        $first = Workflow::insert('lessons', ['course_id' => $course, 'title' => 'First', 'body' => 'Read first', 'position' => 1, 'status' => 'published']);
        $this->actingAs($teacher)->post('/admin/lessons', array_replace($this->payload($course, 'lessons'), ['position' => 2, 'content_format' => 'audio', 'content_source' => 'upload', 'file' => UploadedFile::fake()->create('lesson.mp3', 12, 'audio/mpeg')]))->assertSessionHasNoErrors();
        $lesson = DB::table('lessons')->latest('id')->first(); LearningAccess::trial($learner->id, $course);
        $this->actingAs($learner)->get('/learning/content/lessons/'.$lesson->id)->assertForbidden();
        $data = Snapshot::get($learner)['lessons']->firstWhere('id', $lesson->id);
        $this->assertTrue($data->locked); $this->assertNull($data->viewer_url); $this->assertNull($data->content_url); $this->assertFalse(property_exists($data, 'file_path'));
        Workflow::run($learner, 'complete', ['lesson_id' => $first]);
        $this->get('/learning/content/lessons/'.$lesson->id)->assertOk();
        $this->get('/learning/'.$course)->assertOk()->assertSee('<audio', false);
        $this->actingAs($this->user('participant'))->get('/learning/content/lessons/'.$lesson->id)->assertForbidden();
        $this->actingAs($this->user('instructor'))->get('/learning/content/lessons/'.$lesson->id)->assertForbidden();
        $learner->update(['learning_access_paid' => false]); $this->travel(12)->hours();
        $this->actingAs($learner)->get('/learning/content/lessons/'.$lesson->id)->assertForbidden();
    }

    public function test_source_changes_clear_stale_content_and_uploaded_files_can_be_retained_or_replaced(): void
    {
        Storage::fake('local'); $teacher = $this->user('instructor'); $course = $this->course($teacher);
        $this->actingAs($teacher);
        foreach (['lessons', 'resources'] as $module) {
            $base = $this->payload($course, $module);
            $upload = $base + ['content_format' => 'audio', 'content_source' => 'upload', 'file' => UploadedFile::fake()->create('voice.mp3', 12, 'audio/mpeg')];
            $this->post('/admin/'.$module, $upload)->assertSessionHasNoErrors();
            $item = DB::table($module)->latest('id')->first(); $path = $item->file_path;
            unset($upload['file']);
            $this->put('/admin/'.$module.'/'.$item->id, $upload)->assertSessionHasNoErrors(); Storage::disk('local')->assertExists($path);
            $this->put('/admin/'.$module.'/'.$item->id, $base + ['content_format' => 'audio', 'content_source' => 'url', 'content_url' => 'https://example.test/audio.mp3', 'content_body' => 'Hidden stale text'])->assertSessionHasNoErrors();
            $this->assertDatabaseHas($module, ['id' => $item->id, 'file_path' => null, 'content_body' => null, 'content_url' => 'https://example.test/audio.mp3']); Storage::disk('local')->assertMissing($path);
            $this->put('/admin/'.$module.'/'.$item->id, $base + ['content_format' => 'text', 'content_source' => 'write', 'content_body' => 'Replacement text', 'content_url' => 'https://example.test/stale'])->assertSessionHasNoErrors();
            $this->assertDatabaseHas($module, ['id' => $item->id, 'content_url' => null, 'content_body' => 'Replacement text']);
        }
    }

    public function test_resource_urls_and_text_are_hidden_until_the_associated_lesson_unlocks(): void
    {
        $teacher = $this->user('instructor'); $course = $this->course($teacher); $learner = $this->user('participant');
        $first = Workflow::insert('lessons', ['course_id' => $course, 'title' => 'First', 'body' => 'Text', 'position' => 1, 'status' => 'published']);
        $next = Workflow::insert('lessons', ['course_id' => $course, 'title' => 'Next', 'body' => '', 'content_format' => 'video', 'content_source' => 'url', 'content_url' => 'https://example.test/private.mp4', 'position' => 2, 'status' => 'published']);
        $resource = Workflow::insert('resources', ['course_id' => $course, 'lesson_id' => $next, 'title' => 'Secret audio', 'description' => 'Audio', 'content_format' => 'audio', 'content_source' => 'url', 'content_url' => 'https://example.test/private.mp3', 'status' => 'published']);
        $text = Workflow::insert('resources', ['course_id' => $course, 'lesson_id' => $next, 'title' => 'Secret text', 'description' => 'Text', 'content_format' => 'text', 'content_source' => 'write', 'content_body' => 'Hidden resource body', 'status' => 'published']);
        LearningAccess::trial($learner->id, $course);
        $this->actingAs($learner)->get('/learning/'.$course)->assertOk()->assertDontSee('https://example.test/private')->assertDontSee('Hidden resource body');
        $this->get('/learning/content/resources/'.$resource)->assertForbidden(); $this->get('/learning/content/resources/'.$text)->assertForbidden();
        Workflow::run($learner, 'complete', ['lesson_id' => $first]);
        $this->get('/learning/content/resources/'.$resource)->assertRedirect('https://example.test/private.mp3');
        $this->get('/learning/content/resources/'.$text)->assertOk()->assertHeader('Content-Type', 'text/plain; charset=UTF-8')->assertSee('Hidden resource body');
        $this->get('/learning/'.$course)->assertOk()->assertSee('Hidden resource body')->assertSee('<audio', false);
    }
}
