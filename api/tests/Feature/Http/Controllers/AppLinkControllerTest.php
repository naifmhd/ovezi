<?php

use App\Models\GroupInvite;

it('renders a valid invitation with a safe app link and no tracking referrer', function () {
    $token = str_repeat('a', 64);
    GroupInvite::factory()->create(['token_hash' => hash('sha256', $token), 'expires_at' => now()->addDay()]);
    $this->get('/group-invites/accept?token='.$token)->assertOk()
        ->assertSee('ovezi://group-invites/accept?token='.$token, false)
        ->assertHeader('Referrer-Policy', 'no-referrer')->assertSee('Install Ovezi');
});

it('does not offer acceptance of an expired or unknown invitation', function () {
    $this->get('/group-invites/accept?token='.str_repeat('b', 64))->assertOk()->assertSee('This invitation has expired')->assertDontSee('class="email-button"', false);
});

it('refuses verification redirects to another host', function () {
    $this->getJson('/auth/verify-email?verification_url='.rawurlencode('https://example.com/api/v1/auth/email/verify/1/abc'))->assertUnprocessable();
});

it('serves platform association files only for configured identities', function () {
    config(['ovezi.social.apple_team_id' => 'TESTTEAM', 'ovezi.social.apple_client_id' => 'com.ovezi.app', 'ovezi.android_sha256_fingerprints' => ['AA:BB']]);
    $this->getJson('/.well-known/apple-app-site-association')->assertOk()->assertJsonPath('applinks.details.0.appID', 'TESTTEAM.com.ovezi.app');
    $this->getJson('/.well-known/assetlinks.json')->assertOk()->assertJsonPath('0.target.package_name', 'com.ovezi.app');
});
