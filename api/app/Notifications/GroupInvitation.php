<?php

namespace App\Notifications;

use App\Models\GroupInvite;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GroupInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public GroupInvite $invite,
        public string $token,
    ) {
        $this->invite->loadMissing(['group', 'inviter']);
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('app.invite', ['token' => $this->token]);

        return (new MailMessage)
            ->subject("Join {$this->invite->group->name} on Ovezi")
            ->greeting("You're invited to Ovezi")
            ->line("{$this->invite->inviter->name} invited you to join {$this->invite->group->name}.")
            ->action('Open invitation', $url)
            ->line("This invitation expires {$this->invite->expires_at->diffForHumans()}.")
            ->line('Split. Share. Settle.');
    }
}
