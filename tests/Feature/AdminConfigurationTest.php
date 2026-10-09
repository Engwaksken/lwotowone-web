<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Crypt, DB};
use Tests\TestCase;

class AdminConfigurationTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role='admin',array $extra=[]): User
    {
        return User::create(array_merge(['name'=>'Test user','email'=>uniqid().'@example.test','password'=>'Long-test-password','role'=>$role,'status'=>'active'],$extra));
    }

    public function test_bank_fields_are_required_and_unrelated_type_fields_are_not_saved(): void
    {
        $this->actingAs($this->user('manager'));
        $this->get('/admin/site-settings')->assertOk()->assertSee('id="payment-type-fields"',false);
        $base=['name'=>'Bank transfer','provider_type'=>'Bank','currency'=>'UGX','active'=>1];
        $this->post('/admin/site-settings/payment-gateways',$base)->assertSessionHasErrors(['provider','account_name','account_number']);
        $this->post('/admin/site-settings/payment-gateways',$base+['provider'=>'Example Bank','account_name'=>'Organization','account_number'=>'12345','bank_branch'=>'Kampala','swift_code'=>'ABCDUGKA','merchant_code'=>'not-for-bank'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $gateway=DB::table('payment_gateways')->first();
        $this->assertNull($gateway->merchant_code);
        $this->assertSame('Kampala',json_decode($gateway->configuration,true)['bank_branch']);
        $this->actingAs($this->user('participant',['profile_complete'=>true]))->get('/portal/payment')->assertOk()->assertSee('Kampala')->assertSee('ABCDUGKA');
    }

    public function test_api_credentials_are_encrypted_and_never_exposed_to_learners_or_old_input(): void
    {
        $this->actingAs($this->user('manager'));
        $data=['name'=>'IOTEC','provider_type'=>'IOTEC','merchant_code'=>'M123','currency'=>'UGX','active'=>1,'configure_api'=>1,'api_base_url'=>'https://payments.example.test','client_id'=>'client-123','client_secret'=>'private-payment-secret','webhook_secret'=>'private-webhook-secret','api_environment'=>'sandbox'];
        $this->post('/admin/site-settings/payment-gateways',$data)->assertRedirect()->assertSessionHasNoErrors();
        $gateway=DB::table('payment_gateways')->first();
        $this->assertStringNotContainsString('private-payment-secret',$gateway->credentials);
        $credentials=json_decode(Crypt::decryptString($gateway->credentials),true);
        $this->assertSame('private-payment-secret',$credentials['client_secret']);
        $this->assertStringNotContainsString('client_id',$gateway->configuration);
        $this->get('/admin/site-settings')->assertOk()->assertDontSee('private-payment-secret')->assertSee('Configured (encrypted)');
        $this->actingAs($this->user('participant',['profile_complete'=>true]))->get('/portal/payment')->assertOk()->assertDontSee('private-payment-secret')->assertDontSee('client-123');
        $this->actingAs($this->user('manager'));
        $data['client_id']='';
        $this->post('/admin/site-settings/payment-gateways',$data)->assertSessionHasErrors('client_id');
        $this->assertNull(session()->getOldInput('client_secret'));
        $this->assertNull(session()->getOldInput('webhook_secret'));
    }

    public function test_custom_font_is_applied_to_public_and_auth_layouts_and_css_injection_is_rejected(): void
    {
        $data=['primary_color'=>'#123456','accent_color'=>'#abcdef','font_family'=>'My Custom Font','font_url'=>'https://fonts.example.test/custom.woff2','font_size'=>17];
        $this->actingAs($this->user())->put('/admin/site-settings',$data)->assertRedirect()->assertSessionHasNoErrors();
        $this->get('/')->assertOk()->assertSee('--site-font-family:"My Custom Font"',false)->assertSee('@font-face',false);
        auth()->logout();
        $this->get('/login')->assertOk()->assertSee('--site-font-family:"My Custom Font"',false)->assertSee('@font-face',false);
        $this->actingAs($this->user());$data['font_family']='Arial;}body{display:none';
        $this->put('/admin/site-settings',$data)->assertSessionHasErrors('font_family');
        $this->actingAs($this->user('manager'))->put('/admin/site-settings',$data)->assertForbidden();
    }

    public function test_page_status_filter_returns_only_matching_rows_and_preserves_pagination(): void
    {
        for($i=0;$i<22;$i++)DB::table('pages')->insert(['title'=>'Draft '.$i,'slug'=>'draft-'.$i,'body'=>'Text','status'=>'draft','created_at'=>now(),'updated_at'=>now()]);
        DB::table('pages')->insert(['title'=>'Published article','slug'=>'public','body'=>'Text','status'=>'published','created_at'=>now(),'updated_at'=>now()]);
        $this->actingAs($this->user())->get('/admin/pages?status=draft')->assertOk()->assertViewHas('rows',fn($rows)=>$rows->total()===22&&$rows->every(fn($row)=>$row->status==='draft'))->assertDontSee('Published article')->assertSee('status=draft');
        $this->get('/admin/pages?status=published')->assertOk()->assertSee('Published article')->assertViewHas('rows',fn($rows)=>$rows->total()===1&&$rows->first()->status==='published');
        $this->getJson('/admin/pages?status=invalid')->assertUnprocessable();
    }

    public function test_mobile_merchant_and_other_payment_types_validate_their_own_fields(): void
    {
        $this->actingAs($this->user('manager'));
        $common=['name'=>'Payment','currency'=>'UGX','active'=>1];
        $this->post('/admin/site-settings/payment-gateways',$common+['provider_type'=>'Mobile Money','provider'=>'MTN'])->assertSessionHasErrors(['account_name','account_number']);
        $this->post('/admin/site-settings/payment-gateways',$common+['provider_type'=>'Mobile Money','provider'=>'MTN','account_name'=>'Organization','account_number'=>'+256700000000','bank_branch'=>'Ignored'])->assertSessionHasNoErrors();
        $this->assertStringNotContainsString('bank_branch',DB::table('payment_gateways')->first()->configuration);
        $this->post('/admin/site-settings/payment-gateways',$common+['provider_type'=>'Merchant Code','provider'=>'Network','account_name'=>'Organization'])->assertSessionHasErrors('merchant_code');
        $this->post('/admin/site-settings/payment-gateways',$common+['provider_type'=>'Merchant Code','provider'=>'Network','account_name'=>'Organization','merchant_code'=>'1234'])->assertSessionHasNoErrors();
        $this->post('/admin/site-settings/payment-gateways',$common+['provider_type'=>'Other','provider'=>'Service','instructions'=>'Pay using the link','payment_url'=>'javascript:alert(1)'])->assertSessionHasErrors('payment_url');
        $this->post('/admin/site-settings/payment-gateways',$common+['provider_type'=>'Other','provider'=>'Service','instructions'=>'Pay using the link','payment_url'=>'https://payments.example.test/pay'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('payment_gateways',3);
    }
}
