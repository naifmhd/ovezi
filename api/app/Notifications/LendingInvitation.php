<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LendingInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $senderName) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('An invitation to connect on Ovezi')
            ->line($this->senderName.' would like to share lending records with you.')
            ->line('Sign in to Ovezi and verify this email address, then open Lending to review the invitation. Accepting a connection does not acknowledge a loan.')
            ->action('Open Ovezi', route('app.lending'))->line('The invitation expires in 14 days.');
    }
}
