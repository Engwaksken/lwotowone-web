<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MelManagementTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $role): User
    {
        return User::create([
            'name' => ucfirst($role), 'email' => uniqid().'@example.test',
            'password' => 'A-long-test-password', 'role' => $role, 'status' => 'active',
        ]);
    }

    public function test_managers_can_manage_payment_methods_and_learners_only_see_active_methods(): void
    {
        $manager = $this->account('manager');
        $this->actingAs($this->account('participant'))->get('/admin/mel')->assertForbidden();
        $this->actingAs($manager)->get('/admin/mel')->assertOk()->assertSee('Overview')->assertSee('Learner gender distribution')->assertSee('Participation and inclusion')->assertDontSee('Enrollment cohorts');
        $this->get('/admin/site-settings')->assertOk()->assertSee('Payment gateways and methods');
        $this->get('/admin/enrollment')->assertOk()->assertSee('Enrollment cohorts')->assertSee('Refugee settlements');
        $this->post('/admin/site-settings/payment-gateways', [
            'name' => 'IOTEC Tuition', 'provider_type' => 'IOTEC', 'provider' => 'IOTEC',
            'merchant_code' => 'MERCHANT-42', 'currency' => 'UGX', 'amount' => 50000,
            'instructions' => 'Use your learner number as reference.', 'active' => 1,
        ])->assertRedirect();
        $this->assertDatabaseHas('payment_gateways', [
            'name' => 'IOTEC Tuition', 'merchant_code' => 'MERCHANT-42', 'active' => true,
        ]);
        $participant = $this->account('participant');
        $participant->update(['profile_complete' => true]);
        $this->actingAs($participant)->get('/portal/payment')
            ->assertOk()->assertSee('IOTEC Tuition')->assertSee('MERCHANT-42');
    }

    public function test_supporting_documents_are_manager_only_and_downloadable(): void
    {
        Storage::fake('local');
        $manager = $this->account('manager');
        $this->actingAs($manager)->post('/admin/mel/documents', [
            'category' => 'Finance', 'document' => 'Receipt', 'description' => 'Grant receipt',
            'file' => UploadedFile::fake()->create('receipt.pdf', 20, 'application/pdf'),
        ])->assertRedirect();
        $document = \Illuminate\Support\Facades\DB::table('mel_documents')->first();
        $this->get('/admin/mel/documents/'.$document->id.'/download')->assertOk();
        $this->actingAs($this->account('participant'))->get('/admin/mel/documents/'.$document->id.'/download')->assertForbidden();
    }

    public function test_managers_configure_settlements_and_update_staff_owned_learner_outcomes(): void
    {
        $manager = $this->account('manager');
        $participant = $this->account('participant');
        $this->actingAs($manager)->post('/admin/enrollment/settlements', ['name' => 'Nakivale'])->assertRedirect();
        $this->actingAs($participant)->get('/profile')->assertOk()->assertSee('Settlement')->assertSee('Nakivale');

        $this->actingAs($manager)->put('/admin/enrollment/learners/'.$participant->id.'/outcomes', [
            'verified_outcomes' => 'Completed vocational training',
            'other_verified_outcome' => 'Started a cooperative',
            'after_work_status' => 'Self-employed',
            'after_work_pathway' => 'Enterprise',
        ])->assertRedirect();
        $this->assertDatabaseHas('users', [
            'id' => $participant->id, 'verified_outcomes' => 'Completed vocational training',
            'other_verified_outcome' => 'Started a cooperative', 'after_work_status' => 'Self-employed',
            'after_work_pathway' => 'Enterprise',
        ]);
        $this->actingAs($participant)->put('/admin/enrollment/learners/'.$participant->id.'/outcomes', [])->assertForbidden();
    }
}
