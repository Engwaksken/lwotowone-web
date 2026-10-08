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

    public function test_signup_requires_profile_then_payment_before_learning_access(): void
    {
        $learner = $this->learner();
        $this->actingAs($learner)->get('/dashboard')->assertRedirect('/profile');
        $this->post('/profile', [
            'name' => 'Learner Name', 'phone' => '+256700000000', 'learner_no' => 'LW-1001',
            'enrollment_category' => 'Youth', 'gender' => 'Female', 'location' => 'Kampala',
            'urban_rural' => 'Urban', 'learner_age' => 22, 'refugee' => 0, 'pwd' => 0,
            'education_level' => 'Secondary', 'learner_status' => 'Active',
        ])->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $learner->id, 'profile_complete' => true, 'learning_access_paid' => false]);
        $this->get('/portal/learn')->assertRedirect('/portal/payment');
        $token = $learner->createToken('test')->plainTextToken;
        $this->withToken($token)->getJson('/api/snapshot')->assertForbidden();
        $this->postJson('/actions/enrol', ['course_id' => 1])->assertForbidden();

        $learner->refresh()->update(['learning_access_paid' => true]);
        $this->get('/portal/learn')->assertOk();
        $this->withToken($token)->getJson('/api/snapshot')->assertOk();
    }

    public function test_course_and_certificate_routes_require_confirmed_access(): void
    {
        $learner = $this->learner();
        $learner->update(['profile_complete' => true]);
        $course = Workflow::insert('courses', [
            'title' => 'Learning course', 'program_id' => Workflow::insert('programs', [
                'title' => 'Programme', 'slug' => uniqid(), 'category' => 'TVET',
                'summary' => 'Programme', 'body' => 'Programme', 'status' => 'published',
            ]),
            'instructor_id' => User::create([
                'name' => 'Instructor', 'email' => uniqid().'@example.test', 'password' => 'A-long-test-password',
                'role' => 'instructor', 'status' => 'active',
            ])->id,
            'summary' => 'Course', 'duration_hours' => 1, 'status' => 'published',
        ]);
        DB::table('enrolments')->insert(['user_id' => $learner->id, 'course_id' => $course, 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($learner)->get('/learning/'.$course)->assertForbidden();
        $this->get('/certificates/'.$course)->assertForbidden();
    }
}
