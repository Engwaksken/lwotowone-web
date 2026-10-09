<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class HelpAndBrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_help_pages_and_accessibility_support_are_available(): void
    {
        $this->get('/faq')->assertOk()->assertSee('Frequently asked questions')->assertSee('Can I use the platform if I have a disability?');
        $this->get('/user-guide')->assertOk()->assertSee('Your guide to getting started')->assertSee('Set up your profile');
        $this->get('/')->assertOk()->assertSee('Accessibility options')->assertSee('Need help?')->assertSee('/faq')->assertDontSee('class="public-stat-strip"',false)->assertSee('class="site-footer"',false)->assertSee('name="a11y_text_size"',false)->assertSee('name="a11y_colors"',false);
        $this->get('/login')->assertOk()->assertSee('Accessibility tools')->assertSee('Need help?')->assertDontSee('auth-stat-strip');
    }

    public function test_admin_can_update_site_appearance_and_non_admin_cannot(): void
    {
        $admin = User::create(['name'=>'Admin','email'=>'admin-help@example.test','password'=>Hash::make('A-long-test-password'),'role'=>'admin','status'=>'active']);
        $manager = User::create(['name'=>'Manager','email'=>'manager-help@example.test','password'=>Hash::make('A-long-test-password'),'role'=>'manager','status'=>'active']);

        $this->actingAs($manager)->get('/admin/site-settings')->assertOk()->assertSee('Payment gateways and methods')->assertDontSee('Choose an accessible brand colour');
        $this->actingAs($admin)->get('/admin/site-settings')->assertOk()->assertSee('Website appearance')->assertSee('role="tab"',false)->assertSee('Payment gateway settings');
        $this->get('/dashboard')->assertOk()->assertSee('Dashboard')->assertDontSee('class="site-footer"',false)->assertSee('management-list')->assertSee('Settings')->assertSee('Payment gateway settings');
        $listing=$this->get('/admin/programs')->assertOk();$html=$listing->getContent();
        $this->assertLessThan(strpos($html,'class="public-stat-strip"'),strpos($html,'<h1>Programmes</h1>'));
        $this->assertLessThan(strpos($html,'class="filter-form"'),strpos($html,'class="public-stat-strip"'));
        $this->put('/admin/site-settings',[
            'primary_color'=>'#123456','accent_color'=>'#fedcba','font_family'=>'Georgia','font_size'=>18,
        ])->assertRedirect();

        $this->assertDatabaseHas('settings',['key'=>'primary_color','value'=>'#123456']);
        $this->assertDatabaseHas('settings',['key'=>'accent_color','value'=>'#fedcba']);
        $this->assertDatabaseHas('settings',['key'=>'font_family','value'=>'Georgia']);
        $this->assertDatabaseHas('settings',['key'=>'font_size','value'=>'18']);
        $this->get('/')->assertSee('--green:#123456',false)->assertSee('--site-font-size:18px',false);
    }

    public function test_admin_can_upload_logo_and_png_favicon(): void
    {
        $admin = User::create(['name'=>'Admin','email'=>'admin-brand@example.test','password'=>Hash::make('A-long-test-password'),'role'=>'admin','status'=>'active']);
        $image = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/lZkAAAAASUVORK5CYII=');
        $this->actingAs($admin)->put('/admin/site-settings',[
            'primary_color'=>'#175742','accent_color'=>'#dfb452','font_family'=>'Arial','font_size'=>16,
            'logo'=>UploadedFile::fake()->createWithContent('logo.png',$image),
            'favicon'=>UploadedFile::fake()->createWithContent('favicon.png',$image),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $logo = \Illuminate\Support\Facades\DB::table('settings')->where('key','site_logo')->value('value');
        $favicon = \Illuminate\Support\Facades\DB::table('settings')->where('key','site_favicon')->value('value');
        $this->assertFileExists(public_path(ltrim($logo,'/')));
        $this->assertFileExists(public_path(ltrim($favicon,'/')));
        @unlink(public_path(ltrim($logo,'/')));
        @unlink(public_path(ltrim($favicon,'/')));
        @rmdir(public_path('site-branding'));
    }
}
