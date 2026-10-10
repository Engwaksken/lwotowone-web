<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Workflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LearnerAccessTest extends TestCase
{
    use RefreshDatabase;

    private function learner(): User
    {
        return User::create([
            'name' => 'New learner', 'email' => uniqid().'@example.test',
            'password' => 'A-long-test-password', 'role' => 'participant', 'status' => 'active',
        ]);
    }

    private function course(): int
    {
        $program = Workflow::insert('programs', [
            'title' => 'Programme', 'slug' => uniqid(), 'category' => 'TVET',
            'summary' => 'Programme', 'body' => 'Programme', 'status' => 'published',
        ]);
        $instructor = User::create([
            'name' => 'Instructor', 'email' => uniqid().'@example.test', 'password' => 'A-long-test-password',
            'role' => 'instructor', 'status' => 'active',
        ]);
        return Workflow::insert('courses', [
            'title' => 'Learning course', 'program_id' => $program, 'instructor_id' => $instructor->id,
            'summary' => 'Course', 'duration_hours' => 1, 'status' => 'published',
        ]);
    }

    public function test_signup_requires_profile_then_payment_before_learning_access(): void
    {
        $learner = $this->learner();
        $course = $this->course();
        $this->actingAs($learner)->get('/profile')->assertOk()->assertSee('Personal and course')->assertSee('Background')->assertSee('Employment and goals')->assertSee('Course selection')->assertSee('Select education level')->assertSee('Are you employed?')->assertDontSee('Youth in Work before')->assertDontSee('Verified learner outcomes');
        $this->get('/dashboard')->assertRedirect('/profile');
        $this->post('/profile', [
            'name' => 'Learner Name', 'phone' => '+256700000000', 'selected_course_id' => $course,
            'gender' => 'Female', 'location' => 'Kampala',
            'urban_rural' => 'Urban', 'learner_age' => 22, 'refugee' => 0, 'pwd' => 0,
            'education_level' => 'O-Level', 'employed' => 0,
        ])->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $learner->id, 'profile_complete' => true, 'learning_access_paid' => false, 'learner_no' => null, 'enrollment_category' => null, 'enrollment_date' => null]);
        $this->get('/portal/learn')->assertOk();
        $this->travel(25)->hours();
        $this->get('/portal/learn')->assertRedirect('/portal/payment');
        $token = $learner->createToken('test')->plainTextToken;
        $this->withToken($token)->getJson('/api/snapshot')->assertForbidden();
        $this->postJson('/actions/enrol', ['course_id' => $course])->assertForbidden();

        $this->post('/profile', [
            'name' => 'Learner Name', 'phone' => '+256700000000', 'selected_course_id' => $course,
            'gender' => 'Female', 'location' => 'Kampala', 'urban_rural' => 'Urban', 'learner_age' => 22,
            'refugee' => 0, 'pwd' => 0, 'education_level' => 'Secondary', 'employed' => 1,
        ])->assertSessionHasErrors('employer_name');

        $this->post('/profile', [
            'name' => 'Learner Name', 'phone' => '+256700000000', 'selected_course_id' => $course,
            'gender' => 'Female', 'location' => 'Kampala', 'urban_rural' => 'Urban', 'learner_age' => 22,
            'refugee' => 0, 'pwd' => 0, 'education_level' => 'O-Level',
            'employed' => 1, 'employer_name' => 'Lwotowone Farms',
        ])->assertRedirect();
        $this->assertDatabaseHas('users',['id'=>$learner->id,'employed'=>true,'employer_name'=>'Lwotowone Farms']);

        $manager = User::create(['name'=>'Manager','email'=>uniqid().'@example.test','password'=>'A-long-test-password','role'=>'manager','status'=>'active']);
        $this->actingAs($manager)->post('/admin/enrollment/cohorts', [
            'name'=>'2026 Youth', 'enrollment_category'=>'Youth', 'learner_number_prefix'=>'LW',
            'learner_number_format'=>'{prefix}-{year}-{sequence}', 'next_sequence'=>1, 'sequence_padding'=>4, 'active'=>1,
        ])->assertRedirect();
        $cohort = DB::table('mel_cohorts')->value('id');
        $this->post('/admin/enrollment/learners/'.$learner->id.'/confirm-payment',['cohort_id'=>$cohort])->assertRedirect();
        $this->assertDatabaseHas('users',['id'=>$learner->id,'learning_access_paid'=>true,'learner_no'=>'LW-'.now()->format('Y').'-0001','enrollment_category'=>'Youth','learner_status'=>'Active']);
        $this->assertDatabaseHas('enrolments',['user_id'=>$learner->id,'course_id'=>$course]);
        $learner->refresh();
        $this->actingAs($learner);
        $this->get('/portal/learn')->assertOk();
        $this->withToken($token)->getJson('/api/snapshot')->assertOk();
    }

    public function test_course_and_certificate_routes_require_confirmed_access(): void
    {
        $learner = $this->learner();
        $learner->update(['profile_complete' => true]);
        $course = $this->course();
        DB::table('enrolments')->insert(['user_id' => $learner->id, 'course_id' => $course, 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($learner)->get('/learning/'.$course)->assertForbidden();
        $this->get('/certificates/'.$course)->assertForbidden();
    }
}
