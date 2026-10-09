<?php
namespace Tests\Feature;

use App\Models\User;
use App\Services\Workflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB,Schema};
use Tests\TestCase;

class JourneyMigrationRetryTest extends TestCase
{
    use RefreshDatabase;

    public function test_partial_migration_can_resume_without_losing_data_or_marking_trials_as_paid(): void
    {
        $teacher=User::create(['name'=>'Instructor','email'=>'teacher@example.test','password'=>'Long-test-password','role'=>'instructor','status'=>'active']);
        $learner=User::create(['name'=>'Learner','email'=>'learner@example.test','password'=>'Long-test-password','role'=>'participant','status'=>'active','learning_access_paid'=>true]);
        $program=Workflow::insert('programs',['title'=>'Program','slug'=>'program','summary'=>'Text','body'=>'Text','status'=>'published']);
        $course=Workflow::insert('courses',['title'=>'Course','program_id'=>$program,'instructor_id'=>$teacher->id,'summary'=>'Text','duration_hours'=>1,'status'=>'published']);
        $module=Workflow::insert('course_modules',['course_id'=>$course,'title'=>'Existing module','position'=>1,'status'=>'published']);
        $enrollment=Workflow::insert('enrolments',['user_id'=>$learner->id,'course_id'=>$course,'trial_started_at'=>now(),'trial_expires_at'=>now()->addHours(12)]);
        foreach(['course_certificates','certificate_templates','call_applications','application_calls'] as $table)Schema::drop($table);
        $migration=require database_path('migrations/2026_10_09_000002_create_learner_journey.php');
        $migration->up();
        foreach(['application_calls','call_applications','certificate_templates','course_certificates'] as $table)$this->assertTrue(Schema::hasTable($table));
        $this->assertDatabaseHas('course_modules',['id'=>$module,'title'=>'Existing module']);
        $this->assertDatabaseHas('enrolments',['id'=>$enrollment,'payment_confirmed_at'=>null]);
        $confirmedAt=now()->subHour()->toDateTimeString();DB::table('enrolments')->where('id',$enrollment)->update(['payment_confirmed_at'=>$confirmedAt]);
        $migration->up();
        $this->assertDatabaseHas('enrolments',['id'=>$enrollment,'payment_confirmed_at'=>$confirmedAt]);
        $this->assertDatabaseCount('course_modules',1);
    }
}
