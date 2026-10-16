<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LendingUpdate extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $contactId) {}

    public function via(object $notifiable): array
    {
        return ! $notifiable->trashed() && ($notifiable->notificationPreference?->payment_received ?? true) ? ['mail'] : [];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('A shared lending record was updated')
            ->line('Open Lending in Ovezi to review the latest loan or repayment activity.')
            ->action('Open Ovezi', route('app.lending'));
    }
}
