<?php
namespace App\Notifications;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
class PlatformNotice extends Notification implements ShouldQueue {
 use Queueable;
 public function __construct(public string $title,public string $body){}
 public function via($notifiable): array {return ['database','mail'];}
 public function toArray($notifiable): array {return ['title'=>$this->title,'body'=>$this->body];}
 public function toMail($notifiable): MailMessage {return (new MailMessage)->subject($this->title)->line($this->body)->action('Open your dashboard',url('/dashboard'));}
}
