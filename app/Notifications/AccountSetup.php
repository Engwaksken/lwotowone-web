<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\{DB,URL};

class AccountSetup extends Notification
{
    use Queueable;

    public function __construct(private readonly ?string $temporaryPassword = null) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        \App\Http\Middleware\ApplyMailSettings::apply();
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addDay(),
            ['id' => $notifiable->getKey(), 'hash' => sha1($notifiable->email)],
        );

        $mail = (new MailMessage)->subject('Verify your Lwotowone account');

        $logo = DB::table('settings')->where('key', 'site_logo')->value('value');
        $primary = DB::table('settings')->where('key', 'primary_color')->value('value');
        $brandName = config('app.name', 'Lwotowone Enterprises Ltd');

        $data = [
            'name' => $notifiable->name,
            'email' => $notifiable->email,
            'temporaryPassword' => $this->temporaryPassword,
            'verificationUrl' => $verificationUrl,
            'brandName' => $brandName,
            'logoUrl' => $logo ? (str_starts_with($logo, 'http') ? $logo : url($logo)) : null,
            'primaryColor' => preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $primary) ? $primary : '#175742',
        ];

        return $mail->view('emails.account-setup', $data)
            ->text('emails.account-setup-text', $data);
    }
}
