<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\AccountSetup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Crypt,DB,Hash,Mail,Storage};
use Tests\TestCase;

class SystemSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => Hash::make('A-long-test-password'), 'role' => 'admin', 'status' => 'active']);
    }

    public function test_admin_can_save_encrypted_smtp_credentials_and_send_test_mail(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $this->actingAs($admin)->put('/admin/site-settings/email', [
            'mail_driver' => 'smtp', 'mail_host' => 'smtp.example.test', 'mail_port' => 587,
            'mail_scheme' => 'smtp', 'mail_username' => 'mailer', 'mail_password' => 'secret-password',
            'mail_from_address' => 'hello@example.test', 'mail_from_name' => 'Lwotowone',
        ])->assertRedirect('/admin/site-settings#email-settings');

        $stored = DB::table('settings')->where('key', 'mail_password')->value('value');
        $this->assertStringStartsWith('enc:', $stored);
        $this->assertSame('secret-password', Crypt::decryptString(substr($stored, 4)));
        $this->post('/admin/site-settings/email/test')->assertRedirect('/admin/site-settings#email-settings');
        $this->assertSame('smtp.example.test', config('mail.mailers.smtp.host'));
    }

    public function test_branded_account_email_passes_brand_data_to_both_templates(): void
    {
        $mail = (new AccountSetup('Ab3dEf7x'))->toMail($this->admin());

        $this->assertSame('emails.account-setup', $mail->view['html']);
        $this->assertSame('emails.account-setup-text', $mail->view['text']);
        $this->assertArrayHasKey('primaryColor', $mail->viewData);
        $this->assertArrayHasKey('logoUrl', $mail->viewData);
    }

    public function test_admin_can_create_and_download_a_local_backup(): void
    {
        if (! class_exists(\ZipArchive::class)) {
            $this->markTestSkipped('The PHP zip extension is required to create backups.');
        }

        Storage::fake('local');
        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/site-settings/backups/run', ['destination' => 'local'])
            ->assertRedirect('/admin/site-settings#backup-panel-local');

        $files = Storage::disk('local')->files('backups');
        $this->assertCount(1, $files);
        $filename = basename($files[0]);
        $this->get('/admin/site-settings/backups/'.$filename)->assertOk();
    }
}
