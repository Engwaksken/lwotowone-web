<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Workflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BulkEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role='participant', array $extra=[]): User
    {
        return User::create(array_merge(['name'=>ucfirst($role),'email'=>uniqid().'@example.test','password'=>'Long-test-password','role'=>$role,'status'=>'active'],$extra));
    }

    private function course(): int
    {
        $program=Workflow::insert('programs',['title'=>'Program','slug'=>uniqid(),'category'=>'TVET','summary'=>'Text','body'=>'Text','status'=>'published']);
        return Workflow::insert('courses',['program_id'=>$program,'title'=>'Course','summary'=>'Text','instructor_id'=>$this->user('instructor')->id,'duration_hours'=>1,'status'=>'published']);
    }

    private function cohort(bool $active=true): int
    {
        return DB::table('mel_cohorts')->insertGetId(['name'=>uniqid('Cohort'),'enrollment_category'=>'Youth','learner_number_prefix'=>'LW','learner_number_format'=>'{prefix}-{year}-{sequence}','sequence_padding'=>4,'next_sequence'=>1,'active'=>$active,'created_at'=>now(),'updated_at'=>now()]);
    }

    private function import(string $csv, ?int $cohort=null)
    {
        return $this->post('/admin/enrollment/import',['csv'=>UploadedFile::fake()->createWithContent('enrollment.csv',$csv),'cohort_id'=>$cohort]);
    }

    public function test_manager_can_download_template_and_import_with_default_cohort_idempotently(): void
    {
        $cohort=$this->cohort();$course=$this->course();
        $a=$this->user('participant',['profile_complete'=>true,'selected_course_id'=>$course]);
        $b=$this->user('participant',['profile_complete'=>true,'selected_course_id'=>$course]);
        $this->actingAs($this->user('manager'))->get('/admin/enrollment')->assertOk()->assertSee('Download CSV template')->assertSee('Enroll selected learners');
        $template=$this->get('/admin/enrollment/template.csv')->assertOk()->assertDownload('enrollment-template.csv');
        $this->assertStringContainsString('email,cohort_id',$template->streamedContent());
        $csv="\xEF\xBB\xBFemail,cohort_id\r\n{$a->email},\r\n{$b->email},\r\n";
        $this->import($csv,$cohort)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users',['id'=>$a->id,'learning_access_paid'=>true,'cohort_id'=>$cohort,'learner_no'=>'LW-'.now()->year.'-0001']);
        $this->assertDatabaseHas('users',['id'=>$b->id,'learner_no'=>'LW-'.now()->year.'-0002']);
        $this->assertDatabaseCount('enrolments',2);
        $this->import($csv,$cohort)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('enrolments',2);
        $this->assertDatabaseHas('mel_cohorts',['id'=>$cohort,'next_sequence'=>3]);
        $this->assertDatabaseCount('audit_logs',2);
    }

    public function test_invalid_row_stops_all_changes_and_reports_row_number(): void
    {
        $cohort=$this->cohort();$learner=$this->user('participant',['profile_complete'=>true,'selected_course_id'=>$this->course()]);
        $this->actingAs($this->user('manager'));
        $this->import("email,cohort_id\n{$learner->email},$cohort\nmissing@example.test,$cohort\n")->assertSessionHasErrors('csv');
        $this->assertStringContainsString('Row 3',session('errors')->first('csv'));
        $this->assertDatabaseHas('users',['id'=>$learner->id,'learning_access_paid'=>false]);
        $this->assertDatabaseHas('mel_cohorts',['id'=>$cohort,'next_sequence'=>1]);
        $this->assertDatabaseCount('enrolments',0);
    }

    public function test_duplicate_emails_malformed_csv_and_inactive_cohorts_are_rejected(): void
    {
        $cohort=$this->cohort(false);$learner=$this->user('participant',['profile_complete'=>true,'selected_course_id'=>$this->course()]);
        $this->actingAs($this->user('manager'));
        $this->import("email,cohort_id\n{$learner->email},$cohort\n")->assertSessionHasErrors('csv');
        DB::table('mel_cohorts')->where('id',$cohort)->update(['active'=>true]);
        $this->import("email,cohort_id\n{$learner->email},$cohort\n".strtoupper($learner->email).",$cohort\n")->assertSessionHasErrors('csv');
        $this->import("email,cohort_id\n{$learner->email},$cohort,unexpected\n")->assertSessionHasErrors('csv');
        $this->import("name,email\nLearner,{$learner->email}\n")->assertSessionHasErrors('csv');
        $this->assertDatabaseCount('enrolments',0);
    }

    public function test_bulk_selection_is_atomic_and_cannot_enroll_staff_or_incomplete_profiles(): void
    {
        $cohort=$this->cohort();$learner=$this->user('participant',['profile_complete'=>true,'selected_course_id'=>$this->course()]);
        $incomplete=$this->user();$staff=$this->user('instructor');
        $this->actingAs($this->user('manager'));
        $this->post('/admin/enrollment/bulk',['cohort_id'=>$cohort,'learner_ids'=>[$learner->id,$staff->id]])->assertSessionHasErrors('enrollment');
        $this->post('/admin/enrollment/bulk',['cohort_id'=>$cohort,'learner_ids'=>[$learner->id,$incomplete->id]])->assertSessionHasErrors('enrollment');
        $this->assertDatabaseCount('enrolments',0);
        $this->assertDatabaseHas('mel_cohorts',['id'=>$cohort,'next_sequence'=>1]);
        $this->post('/admin/enrollment/bulk',['cohort_id'=>$cohort,'learner_ids'=>[$learner->id]])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('enrolments',1);
    }

    public function test_import_and_bulk_routes_are_manager_only(): void
    {
        $this->actingAs($this->user())->get('/admin/enrollment/template.csv')->assertForbidden();
        $this->post('/admin/enrollment/import',[])->assertForbidden();
        $this->post('/admin/enrollment/bulk',[])->assertForbidden();
    }

    public function test_enrollment_status_filters_match_learner_access_state(): void
    {
        $course=$this->course();
        $pending=$this->user('participant',['profile_complete'=>true,'selected_course_id'=>$course]);
        $enrolled=$this->user('participant',['profile_complete'=>true,'selected_course_id'=>$course,'learning_access_paid'=>true]);
        $incomplete=$this->user();
        $this->actingAs($this->user('manager'))->get('/admin/enrollment?status=pending')->assertOk()->assertViewHas('outcomeLearners',fn($rows)=>$rows->pluck('id')->all()===[$pending->id]);
        $this->get('/admin/enrollment?status=enrolled')->assertOk()->assertViewHas('outcomeLearners',fn($rows)=>$rows->pluck('id')->all()===[$enrolled->id]);
        $this->get('/admin/enrollment?status=incomplete')->assertOk()->assertViewHas('outcomeLearners',fn($rows)=>$rows->pluck('id')->all()===[$incomplete->id]);
    }
}
