<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ReleaseCheck extends Command
{
    protected $signature = 'ovezi:release-check';

    protected $description = 'Check release configuration without exposing values or contacting external services';

    public function handle(): int
    {
        $checks = [
            'Ovezi public and email branding' => config('app.name') === 'Ovezi',
            'Production environment' => app()->isProduction(),
            'Debug disabled' => ! config('app.debug'),
            'HTTPS public URL' => str_starts_with((string) config('app.url'), 'https://'),
            'Application encryption key' => (bool) config('app.key'),
            'Asynchronous queue connection' => ! in_array(config('queue.default'), ['sync', 'null'], true),
            'Live mail transport' => ! in_array(config('mail.default'), ['log', 'array'], true),
            'Apple client IDs' => config('ovezi.social.apple_client_ids') !== [],
            'Google client IDs' => config('ovezi.social.google_client_ids') !== [],
            'Apple team ID' => (bool) config('ovezi.social.apple_team_id'),
            'Apple key ID' => (bool) config('ovezi.social.apple_key_id'),
            'Apple private key configured' => (bool) config('ovezi.social.apple_private_key'),
            'Android app-link signing fingerprints' => config('ovezi.android_sha256_fingerprints') !== [],
            'Support contact' => filter_var(config('ovezi.support_email'), FILTER_VALIDATE_EMAIL) !== false,
            'Error monitoring DSN' => (bool) config('sentry.dsn'),
        ];
        $this->table(['Release configuration', 'Status'], collect($checks)->map(fn ($ok, $label) => [$label, $ok ? 'PASS' : 'REQUIRED'])->all());
        $this->line('This checks configuration presence only. Verify signing, OAuth, mail/push delivery, app links, queues, private storage, backups, and monitoring in the deployed environment.');

        return in_array(false, $checks, true) ? self::FAILURE : self::SUCCESS;
    }
}
