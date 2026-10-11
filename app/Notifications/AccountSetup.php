<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

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

        $mail = (new MailMessage)
            ->subject('Verify your Lwotowone account')
            ->greeting('Hello '.$notifiable->name.',')
            ->line('Your Lwotowone account has been created. Verify your email address to activate sign-in.');

        if ($this->temporaryPassword !== null) {
            $mail->line('Sign-in email: '.$notifiable->email)
                ->line('Temporary password: '.$this->temporaryPassword)
                ->line('After signing in, you will be required to choose a new password.');
        }

        return $mail->action('Verify email address', $verificationUrl)
            ->line('This verification link expires in 24 hours.');
    }
}
