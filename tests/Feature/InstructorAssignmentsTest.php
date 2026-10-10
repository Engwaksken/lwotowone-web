<?php

namespace Tests\Feature;

use App\Models\{InstructorAssignments, Record, User};
use App\Policies\CoursePolicy;
use App\Services\Workflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Notification, Storage};
use Tests\TestCase;

class InstructorAssignmentsTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'instructor'): User
    {
        return User::create(['name' => 'Test '.$role, 'email' => uniqid().'@example.test', 'password' => 'A-long-test-password', 'role' => $role, 'status' => 'active', 'profile_complete' => true, 'learning_access_paid' => true]);
    }

    private function course(): \App\Models\InstructorRecord
    {
        $program = Workflow::insert('programs', ['title' => 'Program', 'slug' => uniqid(), 'category' => 'TVET', 'summary' => 'Text', 'body' => 'Text', 'status' => 'published']);
        return Record::for('courses')->newQuery()->create($this->payload($program));
    }

    private function payload(int $program): array
    {
        return ['title' => 'Shared course', 'program_id' => $program, 'summary' => 'Text', 'level' => 'Beginner', 'duration_hours' => 2, 'status' => 'published'];
    }

    public function test_admin_can_replace_memberships_and_lead_and_clear_all_assignments(): void
    {
        $course = $this->course(); $first = $this->user(); $second = $this->user();
        $this->actingAs($this->user('admin'))->put('/admin/courses/'.$course->id, $this->payload($course->program_id) + ['instructor_ids' => [(string)$first->id, (string)$second->id], 'lead_instructor_id' => $second->id])->assertRedirect()->assertSessionHasNoErrors();
        $course->refresh();
        $this->assertSame([$first->id, $second->id], $course->assignedInstructorIds());
        $this->assertSame($second->id, $course->resolvedInstructorData()['lead']['id']);
        $this->get('/admin/courses/'.$course->id.'/edit')->assertOk()->assertSee('Assigned instructors');
        $this->put('/admin/courses/'.$course->id, $this->payload($course->program_id) + ['assignments_present' => 1, 'lead_instructor_id' => null])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame([], $course->refresh()->assignedInstructorIds());
        $this->assertNull($course->instructor_id);
        $this->assertDatabaseCount('course_instructor_leads', 0);
    }

    public function test_invalid_assignment_rolls_back_content_and_new_record_creation(): void
    {
        $course = $this->course(); $teacher = $this->user(); $outsider = $this->user();
        InstructorAssignments::sync($course, [$teacher->id], $teacher->id);
        $this->actingAs($this->user('admin'))->put('/admin/courses/'.$course->id, array_replace($this->payload($course->program_id), ['title' => 'Must roll back', 'instructor_ids' => [$teacher->id], 'lead_instructor_id' => $outsider->id]))->assertSessionHasErrors('lead_instructor_id');
        $this->assertSame('Shared course', $course->refresh()->title);
        $this->assertSame($teacher->id, $course->resolvedInstructorData()['lead']['id']);
        $outsider->update(['status' => 'inactive']);
        $this->post('/admin/courses', $this->payload($course->program_id) + ['instructor_ids' => [$outsider->id]])->assertSessionHasErrors('instructor_ids');
        $this->assertDatabaseCount('courses', 1);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_only_admin_can_change_assignments_and_instructor_edits_preserve_memberships(): void
    {
        $course = $this->course(); $first = $this->user(); $second = $this->user();
        InstructorAssignments::sync($course, [$first->id, $second->id], $first->id);
        foreach ([$this->user('manager'), $second] as $user) {
            foreach (['instructor_ids' => [$second->id], 'lead_instructor_id' => $second->id, 'instructor_id' => $second->id, 'assignments_present' => 1] as $field => $value) {
                $this->actingAs($user)->put('/admin/courses/'.$course->id, $this->payload($course->program_id) + [$field => $value])->assertForbidden();
            }
        }
        $this->actingAs($second)->put('/admin/courses/'.$course->id, array_replace($this->payload($course->program_id), ['title' => 'Updated by co-instructor']))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame([$first->id, $second->id], $course->refresh()->assignedInstructorIds());
        $this->assertSame($first->id, $course->resolvedInstructorData()['lead']['id']);
        $this->post('/admin/courses', $this->payload($course->program_id))->assertForbidden();
    }

    public function test_co_instructor_can_manage_content_review_and_read_private_files_until_removed(): void
    {
        Storage::fake('local');
        $course = $this->course(); $first = $this->user(); $second = $this->user(); $learner = $this->user('participant');
        InstructorAssignments::sync($course, [$first->id, $second->id]);
        $assignment = Workflow::insert('assignments', ['course_id' => $course->id, 'title' => 'Practical', 'instructions' => 'Work', 'pass_mark' => 60, 'status' => 'published']);
        $submission = Workflow::insert('submissions', ['assignment_id' => $assignment, 'user_id' => $learner->id, 'body' => 'Evidence', 'file_path' => 'work.txt', 'status' => 'submitted']);
        $resource = Workflow::insert('resources', ['course_id' => $course->id, 'title' => 'Guide', 'description' => 'Text', 'file_path' => 'guide.txt', 'status' => 'draft']);
        Storage::disk('local')->put('guide.txt', 'Guide'); Storage::disk('local')->put('work.txt', 'Evidence');
        $this->actingAs($second)->get('/admin/courses/'.$course->id.'/edit')->assertOk();
        $this->post('/admin/lessons', ['course_id' => $course->id, 'title' => 'Co-instructor lesson', 'body' => 'Text', 'position' => 1, 'status' => 'published'])->assertRedirect()->assertSessionHasNoErrors();
        $this->post('/admin/reviews/submissions/'.$submission, ['score' => 80, 'feedback' => 'Good', 'status' => 'passed'])->assertRedirect();
        $this->assertDatabaseHas('submissions', ['id' => $submission, 'reviewer_id' => $second->id]);
        $this->get('/learning/content/resources/'.$resource)->assertOk();
        $this->get('/files/submissions/'.$submission)->assertOk();
        Workflow::insert('enrolments', ['course_id' => $course->id, 'user_id' => $learner->id, 'payment_confirmed_at' => now()]);
        Workflow::insert('lesson_progress', ['user_id' => $learner->id, 'lesson_id' => DB::table('lessons')->value('id'), 'completed_at' => now()]);
        $this->post('/admin/certificates/'.$course->id.'/recommend', ['user_id' => $learner->id])->assertRedirect();
        $this->assertDatabaseHas('course_certificates', ['course_id' => $course->id, 'recommended_by' => $second->id]);
        InstructorAssignments::sync($course, [$first->id]);
        $this->get('/admin/courses/'.$course->id.'/edit')->assertNotFound();
        $this->get('/files/resources/'.$resource)->assertForbidden();
        $this->get('/files/submissions/'.$submission)->assertForbidden();
        $this->post('/admin/reviews/submissions/'.$submission, ['score' => 80, 'feedback' => 'Good', 'status' => 'passed'])->assertNotFound();
        $this->post('/admin/certificates/'.$course->id.'/recommend', ['user_id' => $learner->id])->assertForbidden();
    }

    public function test_program_lead_and_legacy_column_do_not_grant_course_access(): void
    {
        $course = $this->course(); $teacher = $this->user();
        $program = Record::for('programs')->newQuery()->findOrFail($course->program_id);
        InstructorAssignments::sync($program, [$teacher->id], $teacher->id);
        $course->update(['instructor_id' => $teacher->id]);
        $policy = new CoursePolicy;
        $this->assertFalse($policy->view($teacher, $course));
        $this->actingAs($teacher)->get('/admin/courses/'.$course->id.'/edit')->assertNotFound();
        InstructorAssignments::sync($course, [$teacher->id]);
        $this->assertTrue($policy->view($teacher, $course));
        $teacher->update(['role' => 'mentor']);
        $this->assertFalse($policy->view($teacher, $course));
        $this->assertFalse($course->hasAssignedInstructor($teacher->id));
        $this->actingAs($this->user('manager'))->put('/admin/programs/'.$program->id, ['instructor_ids' => []])->assertForbidden();
    }

    public function test_submission_notifies_all_active_assigned_instructors_once(): void
    {
        Notification::fake();
        $course = $this->course(); $first = $this->user(); $second = $this->user(); $inactive = $this->user(); $learner = $this->user('participant');
        InstructorAssignments::sync($course, [$first->id, $second->id, $inactive->id]);
        $inactive->update(['status' => 'inactive']);
        Workflow::insert('enrolments', ['course_id' => $course->id, 'user_id' => $learner->id, 'payment_confirmed_at' => now()]);
        $assignment = Workflow::insert('assignments', ['course_id' => $course->id, 'title' => 'Practical', 'instructions' => 'Text', 'pass_mark' => 60, 'status' => 'published']);
        $payload = ['assignment_id' => $assignment, 'body' => 'Evidence', 'client_id' => 'e57429a6-96dc-4ae9-8625-b0c53bf82341'];
        Workflow::run($learner, 'submit', $payload); Workflow::run($learner, 'submit', $payload);
        Notification::assertSentToTimes($first, \App\Notifications\PlatformNotice::class, 1);
        Notification::assertSentToTimes($second, \App\Notifications\PlatformNotice::class, 1);
        Notification::assertNotSentTo($inactive, \App\Notifications\PlatformNotice::class);
    }
}
