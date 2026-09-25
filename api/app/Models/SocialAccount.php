<?php

namespace App\Models;

use App\ConnectedAccountProvider;
use Database\Factories\SocialAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'provider', 'provider_user_id', 'provider_email', 'provider_email_verified_at', 'avatar_url', 'refresh_token', 'client_id'])]
#[Hidden(['refresh_token', 'client_id'])]
class SocialAccount extends Model
{
    /** @use HasFactory<SocialAccountFactory> */
    use HasFactory;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'provider' => ConnectedAccountProvider::class,
            'refresh_token' => 'encrypted',
            'provider_email_verified_at' => 'datetime',
        ];
    }
}
