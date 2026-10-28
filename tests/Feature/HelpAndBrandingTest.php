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
        $this->get('/dashboard')->assertOk()->assertSee('Overview')->assertDontSee('Dashboard')->assertDontSee('class="site-footer"',false)->assertSee('management-list')->assertSee('Settings')->assertSee('Payment gateway settings');
        $listing=$this->get('/admin/programs')->assertOk();$html=$listing->getContent();
        $this->assertLessThan(strpos($html,'class="public-stat-strip"'),strpos($html,'<h1>Programmes</h1>'));
        $this->assertLessThan(strpos($html,'class="filter-bar"'),strpos($html,'class="public-stat-strip"'));
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

    public function test_admin_edits_home_page_text_from_website_pages(): void
    {
        $admin = User::create(['name'=>'Admin','email'=>'admin-home@example.test','password'=>Hash::make('A-long-test-password'),'role'=>'admin','status'=>'active']);
        $home = \Illuminate\Support\Facades\DB::table('pages')->where('slug','home')->first();
        $this->assertNotNull($home, 'The migration should add the home page to Website pages.');
        $this->actingAs($admin)->get('/admin/pages')->assertOk()->assertSee($home->title);
        $this->get('/admin/pages/'.$home->id.'/edit')->assertOk()->assertSee('This is the website home page.');
        $this->put('/admin/pages/'.$home->id, ['title'=>'Learn a trade with us','slug'=>'home','status'=>'published','meta_description'=>'Trade courses in Gulu.','body'=>"Short courses near you.\n\nFirst about paragraph.\n\nSecond about paragraph."])->assertRedirect('/admin/pages');
        $this->get('/')->assertOk()->assertSee('<h1>Learn a trade with us</h1>',false)->assertSee('<p class="home-lead">Short courses near you.</p>',false)
            ->assertSee('<p>First about paragraph.</p>',false)->assertSee('<p>Second about paragraph.</p>',false)->assertSee('content="Trade courses in Gulu."',false);
        $this->get('/pages/home')->assertRedirect('/');
        \Illuminate\Support\Facades\DB::table('pages')->where('id',$home->id)->update(['status'=>'draft']);
        $this->get('/')->assertOk()->assertDontSee('Learn a trade with us')->assertSee('How it works here');
    }

    public function test_admin_can_upload_intro_video_shown_on_home_page(): void
    {
        $admin = User::create(['name'=>'Admin','email'=>'admin-video@example.test','password'=>Hash::make('A-long-test-password'),'role'=>'admin','status'=>'active']);
        $manager = User::create(['name'=>'Manager','email'=>'manager-video@example.test','password'=>Hash::make('A-long-test-password'),'role'=>'manager','status'=>'active']);
        $video = fn () => UploadedFile::fake()->createWithContent('intro.mp4', "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom".str_repeat("\x00", 64));

        $this->get('/')->assertOk()->assertDontSee('class="home-video"',false);
        $this->actingAs($manager)->put('/admin/site-settings/intro-video',['intro_video'=>$video(),'intro_video_enabled'=>1])->assertForbidden();
        $this->actingAs($admin)->get('/admin/site-settings')->assertOk()->assertSee('Introduction video')->assertSee('action="/admin/site-settings/intro-video"',false);
        $this->actingAs($admin)->put('/admin/site-settings/intro-video',['intro_video'=>UploadedFile::fake()->create('intro.mov',10,'video/quicktime')])->assertSessionHasErrors('intro_video');

        $this->actingAs($admin)->put('/admin/site-settings/intro-video',[
            'intro_video'=>$video(),'intro_video_title'=>'Meet our team','intro_video_summary'=>'See how we work.','intro_video_enabled'=>1,
        ])->assertRedirect('/admin/site-settings#settings-panel-video')->assertSessionHasNoErrors();

        $path = \Illuminate\Support\Facades\DB::table('settings')->where('key','intro_video')->value('value');
        $this->assertStringStartsWith('/site-media/intro_video-', $path);
        $this->assertFileExists(public_path(ltrim($path,'/')));
        auth()->logout();
        $this->get('/')->assertOk()->assertSee('class="home-video"',false)->assertSee('Meet our team')->assertSee('See how we work.')->assertSee($path);

        $this->actingAs($admin)->put('/admin/site-settings/intro-video',['intro_video_enabled'=>0])->assertSessionHasNoErrors();
        auth()->logout();
        $this->get('/')->assertDontSee('class="home-video"',false);

        $this->actingAs($admin)->putJson('/admin/site-settings/intro-video',['remove_intro_video'=>1,'intro_video_enabled'=>1])->assertOk()->assertJson(['ok'=>true]);
        $this->assertFileDoesNotExist(public_path(ltrim($path,'/')));
        $this->assertSame('0', \Illuminate\Support\Facades\DB::table('settings')->where('key','intro_video_enabled')->value('value'));
        @rmdir(public_path('site-media'));
    }
}
