<?php

namespace App\Jobs;

use App\Services\Social\AppleTokenService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class RevokeAppleToken implements ShouldQueue
{
    use Queueable;

    public int $tries = 8;

    public int $timeout = 30;

    public array $backoff = [60, 300, 900, 3600, 10800];

    public function __construct(public readonly int $revocationId) {}

    public function handle(AppleTokenService $apple): void
    {
        $record = DB::table('apple_token_revocations')->find($this->revocationId);
        if ($record === null) {
            return;
        }
        $apple->revoke(Crypt::decryptString($record->token), $record->client_id);
        DB::table('apple_token_revocations')->where('id', $record->id)->delete();
    }
}
