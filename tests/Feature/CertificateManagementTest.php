<?php

namespace Tests\Feature;

use App\Models\{InstructorAssignments, Record, User};
use App\Services\{CertificatePdf, Workflow};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Storage};
use Tests\TestCase;

class CertificateManagementTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'admin'): User
    {
        return User::create(['name' => 'Test '.$role, 'email' => uniqid().'@example.test', 'password' => 'A-long-test-password', 'role' => $role, 'status' => 'active']);
    }

    private function subjects(): array
    {
        $program = Workflow::insert('programs', ['title' => 'Skills', 'slug' => uniqid(), 'category' => 'TVET', 'summary' => 'Text', 'body' => 'Text', 'status' => 'published']);
        $course = Workflow::insert('courses', ['program_id' => $program, 'title' => 'Enterprise course', 'summary' => 'Text', 'duration_hours' => 4, 'status' => 'published']);
        $event = Workflow::insert('events', ['title' => 'Enterprise workshop', 'description' => 'Text', 'starts_at' => '2026-11-12 09:00:00', 'ends_at' => '2026-11-12 12:30:00', 'location' => 'Kampala', 'capacity' => 30, 'status' => 'published']);
        return [$course, $event];
    }

    private function file(): UploadedFile
    {
        $pdf = app(CertificatePdf::class)->pdf(); $pdf->AddPage('L', 'A4');
        return UploadedFile::fake()->createWithContent('design.pdf', $pdf->Output('design.pdf', 'S'));
    }

    private function createTemplate(string $type, int $id, string $name): void
    {
        $this->post('/admin/certificates', ['name' => $name, 'subject_type' => $type, $type.'_id' => $id, 'template' => $this->file(), '_modal_id' => 'add-certificate'])
            ->assertRedirect('/admin/certificates/'.($type === 'event' ? 'events/' : '').$id.'/template')->assertSessionHasNoErrors();
    }

    public function test_admin_creates_course_and_event_templates_from_modal_and_sees_them_in_one_table(): void
    {
        Storage::fake('local'); [$course, $event] = $this->subjects();
        $this->actingAs($this->user())->get('/admin/certificates')->assertOk()->assertSee('data-dialog-open="add-certificate"', false)->assertSee('Event Name')->assertSee('No certificate templates yet');
        $this->createTemplate('course', $course, 'Course completion design'); $this->createTemplate('event', $event, 'Workshop participation design');
        $this->assertDatabaseHas('certificate_templates', ['name' => 'Course completion design', 'course_id' => $course, 'event_id' => null]);
        $this->assertDatabaseHas('certificate_templates', ['name' => 'Workshop participation design', 'course_id' => null, 'event_id' => $event]);
        $this->assertDatabaseCount('certificate_templates', 2); $this->assertDatabaseCount('course_certificates', 0);
        foreach (DB::table('certificate_templates')->get() as $template) Storage::disk('local')->assertExists($template->file_path);
        $this->get('/admin/certificates')->assertOk()->assertSee('Course completion design')->assertSee('Workshop participation design')->assertSee('Enterprise workshop')->assertSee('/admin/certificates/events/'.$event.'/template', false)->assertSee('Course recommendations');
        $this->assertDatabaseCount('audit_logs', 2);
    }

    public function test_event_editor_saves_styles_and_preview_uses_event_details_instead_of_course_details(): void
    {
        Storage::fake('local'); [$course, $event] = $this->subjects(); $this->actingAs($this->user());
        $this->createTemplate('course', $course, 'Course design'); $this->createTemplate('event', $event, 'Event design');
        $courseTemplate = DB::table('certificate_templates')->where('course_id', $course)->first();
        $this->get('/admin/certificates/events/'.$event.'/template')->assertOk()->assertSee('Event title')->assertSee('Event dates')->assertSee('Issued by')->assertSee('/admin/certificates/events/'.$event.'/background', false);
        $placements = CertificatePdf::defaults(); $placements['course']['font_style'] = 'B'; $placements['course']['color'] = '#225599'; $placements['duration']['enabled'] = true;
        $this->put('/admin/certificates/events/'.$event.'/template', ['name' => 'Updated workshop design', 'placements' => $placements])->assertRedirect()->assertSessionHasNoErrors();
        $saved = DB::table('certificate_templates')->where('event_id', $event)->first();
        $this->assertSame('Updated workshop design', $saved->name); $this->assertSame('#225599', json_decode($saved->placements, true)['course']['color']);
        $this->assertSame($courseTemplate->placements, DB::table('certificate_templates')->where('course_id', $course)->value('placements'));
        $this->get('/admin/certificates/events/'.$event.'/background')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->app->instance(CertificatePdf::class, new class extends CertificatePdf {
            public function pdf(): \setasign\Fpdi\Tcpdf\Fpdi { $pdf = parent::pdf(); $pdf->setCompression(false); return $pdf; }
        });
        $bytes = $this->get('/admin/certificates/events/'.$event.'/preview.pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf')->getContent();
        $this->assertStringStartsWith('%PDF-', $bytes);
        $this->assertStringContainsString(mb_convert_encoding('Enterprise workshop', 'UTF-16BE', 'UTF-8'), $bytes);
        $this->assertStringContainsString(mb_convert_encoding('3.5 hours', 'UTF-16BE', 'UTF-8'), $bytes);
        $this->assertStringNotContainsString(mb_convert_encoding('Enterprise course', 'UTF-16BE', 'UTF-8'), $bytes);
    }

    public function test_creation_rejects_duplicates_mixed_subjects_and_invalid_uploads_without_orphan_files(): void
    {
        Storage::fake('local'); [$course, $event] = $this->subjects(); $this->actingAs($this->user());
        $this->createTemplate('course', $course, 'Original design'); $files = Storage::disk('local')->allFiles();
        $this->post('/admin/certificates', ['name' => 'Duplicate', 'subject_type' => 'course', 'course_id' => $course, 'template' => $this->file(), '_modal_id' => 'add-certificate'])->assertSessionHasErrors('course_id')->assertSessionHasInput('_modal_id', 'add-certificate');
        $this->get('/admin/certificates')->assertOk()->assertSee('data-reopen-dialog="add-certificate"', false);
        $this->post('/admin/certificates', ['name' => 'Mixed', 'subject_type' => 'event', 'course_id' => $course, 'event_id' => $event, 'template' => $this->file()])->assertSessionHasErrors('course_id');
        $this->post('/admin/certificates', ['name' => 'No event', 'subject_type' => 'event', 'template' => $this->file()])->assertSessionHasErrors('event_id');
        $this->post('/admin/certificates', ['name' => 'Corrupt design', 'subject_type' => 'event', 'event_id' => $event, 'template' => UploadedFile::fake()->createWithContent('design.pdf', '%PDF-1.4 not a valid document')])->assertSessionHasErrors('template');
        $this->assertDatabaseCount('certificate_templates', 1); $this->assertSame($files, Storage::disk('local')->allFiles());
    }

    public function test_admin_only_template_mutations_and_event_design_access_are_enforced(): void
    {
        Storage::fake('local'); [$course, $event] = $this->subjects(); $this->actingAs($this->user());
        $this->createTemplate('event', $event, 'Private event design');
        foreach (['manager', 'instructor', 'mentor', 'participant'] as $role) {
            $this->actingAs($this->user($role))->post('/admin/certificates', ['name' => 'Blocked', 'subject_type' => 'course', 'course_id' => $course, 'template' => $this->file()])->assertForbidden();
            foreach (['template', 'background', 'preview.pdf'] as $suffix) $this->get('/admin/certificates/events/'.$event.'/'.$suffix)->assertForbidden();
            $this->put('/admin/certificates/events/'.$event.'/template', ['placements' => CertificatePdf::defaults()])->assertForbidden();
        }
        $this->actingAs($this->user('manager'))->get('/admin/certificates')->assertOk()->assertSee('Private event design')->assertDontSee('data-dialog-open="add-certificate"', false)->assertDontSee('/admin/certificates/events/'.$event.'/template', false);
        $this->assertDatabaseCount('certificate_templates', 1);
    }

    public function test_instructor_table_only_contains_assigned_course_templates(): void
    {
        Storage::fake('local'); [$course, $event] = $this->subjects(); [$otherCourse] = $this->subjects(); $teacher = $this->user('instructor');
        InstructorAssignments::sync(Record::for('courses')->newQuery()->findOrFail($course), [$teacher->id]);
        $this->actingAs($this->user());
        $this->createTemplate('course', $course, 'Assigned course design'); $this->createTemplate('course', $otherCourse, 'Other course design'); $this->createTemplate('event', $event, 'Event-only design');
        $this->actingAs($teacher)->get('/admin/certificates')->assertOk()->assertSee('Assigned course design')->assertDontSee('Other course design')->assertDontSee('Event-only design')->assertDontSee('data-dialog-open="add-certificate"', false);
    }

    public function test_event_background_replacement_cleans_up_the_old_file_and_preserves_template_identity(): void
    {
        Storage::fake('local'); [, $event] = $this->subjects(); $this->actingAs($this->user()); $this->createTemplate('event', $event, 'Workshop design');
        $before = DB::table('certificate_templates')->where('event_id', $event)->first();
        $this->put('/admin/certificates/events/'.$event.'/template', ['name' => 'Replacement design', 'template' => $this->file(), 'placements' => CertificatePdf::defaults()])->assertSessionHasNoErrors();
        $after = DB::table('certificate_templates')->where('event_id', $event)->first();
        $this->assertSame($before->id, $after->id); $this->assertSame($before->created_at, $after->created_at);
        Storage::disk('local')->assertMissing($before->file_path); Storage::disk('local')->assertExists($after->file_path);
        $this->assertDatabaseCount('certificate_templates', 1);
    }

    public function test_migration_preserves_existing_course_designs_and_is_retry_safe(): void
    {
        Storage::fake('local'); [$course] = $this->subjects(); $this->actingAs($this->user()); $this->createTemplate('course', $course, 'Legacy course design');
        $original = DB::table('certificate_templates')->first();
        $migration = require database_path('migrations/2026_10_10_000004_add_event_certificate_templates.php');
        $migration->down(); $migration->up(); $migration->up();
        $after = DB::table('certificate_templates')->first();
        $this->assertSame($original->id, $after->id); $this->assertSame($original->file_path, $after->file_path); $this->assertSame($original->placements, $after->placements);
        $this->assertSame('Enterprise course certificate', $after->name); $this->assertNull($after->event_id); $this->assertDatabaseCount('certificate_templates', 1);
    }

    public function test_rollback_refuses_to_remove_event_designs_before_any_schema_changes(): void
    {
        Storage::fake('local'); [, $event] = $this->subjects(); $this->actingAs($this->user()); $this->createTemplate('event', $event, 'Preserved event design');
        $migration = require database_path('migrations/2026_10_10_000004_add_event_certificate_templates.php');
        try { $migration->down(); $this->fail('Rollback must preserve existing event templates.'); }
        catch (\RuntimeException $e) { $this->assertStringContainsString('event certificate templates exist', $e->getMessage()); }
        $template = DB::table('certificate_templates')->first();
        $this->assertSame('Preserved event design', $template->name); $this->assertSame($event, $template->event_id); Storage::disk('local')->assertExists($template->file_path);
    }
}
