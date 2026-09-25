<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ConfirmAccountDeletion extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Confirm your Ovezi account deletion')
            ->line('Someone requested deletion of your Ovezi account. Review and confirm only if you made this request.')
            ->action('Review account deletion', route('account-deletion.show', $this->token))
            ->line('This link expires in one hour. Opening it does not delete your account.')
            ->line('If you did not request this, ignore this email. Your account will stay active.');
    }
}
