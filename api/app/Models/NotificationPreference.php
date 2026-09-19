<?php

namespace App\Models;

use App\NotificationType;
use Database\Factories\NotificationPreferenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'expense_created', 'payment_received', 'settle_up_reminders'])]
class NotificationPreference extends Model
{
    /** @use HasFactory<NotificationPreferenceFactory> */
    use HasFactory;

    protected $attributes = [
        'expense_created' => true,
        'payment_received' => true,
        'settle_up_reminders' => true,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function allows(NotificationType $type): bool
    {
        return (bool) $this->getAttribute($type->value);
    }

    protected function casts(): array
    {
        return [
            'expense_created' => 'boolean',
            'payment_received' => 'boolean',
            'settle_up_reminders' => 'boolean',
        ];
    }
}
