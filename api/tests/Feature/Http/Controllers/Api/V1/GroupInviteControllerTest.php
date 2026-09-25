<?php

use App\Models\Currency;
use App\Models\Group;
use App\Models\GroupInvite;
use App\Models\GroupMember;
use App\Models\User;
use App\Notifications\GroupInvitation;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

function groupWithOwnerForInvite(User $owner, Currency $currency, array $attributes = []): Group
{
    $group = Group::factory()->for($owner, 'creator')->create([
        'reporting_currency_code' => $currency->code,
        ...$attributes,
    ]);
    GroupMember::factory()->owner()->for($group)->for($owner)->create();

    return $group;
}

describe('management', function () {
    it('requires authentication', function () {
        $this->postJson('/api/v1/groups/1/invites')->assertUnauthorized();
        $this->postJson('/api/v1/group-invites/accept')->assertUnauthorized();
    });

    it('lets an owner create a seven day link invite and only exposes the token once', function () {
        $currency = Currency::factory()->mvr()->create();
        $owner = User::factory()->create(['default_currency_code' => $currency->code]);
        $group = groupWithOwnerForInvite($owner, $currency);
        $token = $owner->createToken('Owner phone');

        $response = $this->withToken($token->plainTextToken)
            ->postJson("/api/v1/groups/{$group->id}/invites");

        $response
            ->assertCreated()
            ->assertJsonPath('data.group_id', $group->id)
            ->assertJsonPath('data.invited_email', null)
            ->assertJsonPath('data.is_expired', false)
            ->assertJsonPath('data.is_revoked', false)
            ->assertJsonMissingPath('data.token_hash');
        $rawToken = $response->json('meta.token');
        expect($rawToken)->toBeString()->toHaveLength(64);

        $invite = GroupInvite::query()->sole();
        expect($invite->token_hash)->toBe(hash('sha256', $rawToken))
            ->and($invite->expires_at->between(now()->addDays(7)->subMinute(), now()->addDays(7)->addMinute()))
            ->toBeTrue();

        $this->withToken($token->plainTextToken)
            ->getJson("/api/v1/groups/{$group->id}/invites")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonMissingPath('data.0.token_hash')
            ->assertJsonMissingPath('meta.token');
    });

    it('normalizes a targeted email and lets the owner revoke a pending invite', function () {
        Notification::fake();
        $currency = Currency::factory()->mvr()->create();
        $owner = User::factory()->create(['default_currency_code' => $currency->code]);
        $group = groupWithOwnerForInvite($owner, $currency);
        $token = $owner->createToken('Owner phone');

        $response = $this->withToken($token->plainTextToken)
            ->postJson("/api/v1/groups/{$group->id}/invites", [
                'invited_email' => ' FRIEND@Example.COM ',
            ]);
        $inviteId = $response->json('data.id');

        $response->assertCreated()->assertJsonPath('data.invited_email', 'friend@example.com');
        $rawToken = $response->json('meta.token');
        Notification::assertSentOnDemand(
            GroupInvitation::class,
            function (GroupInvitation $notification, array $channels, AnonymousNotifiable $notifiable) use ($group, $owner, $rawToken): bool {
                $mail = $notification->toMail($notifiable);

                return $notifiable->routes['mail'] === 'friend@example.com'
                    && $channels === ['mail']
                    && $notification->invite->group_id === $group->id
                    && $mail->subject === "Join {$group->name} on Ovezi"
                    && $mail->actionUrl === route('app.invite', ['token' => $rawToken])
                    && str_contains(implode(' ', $mail->introLines), $owner->name);
            },
        );
        $this->withToken($token->plainTextToken)
            ->deleteJson("/api/v1/groups/{$group->id}/invites/{$inviteId}")
            ->assertOk()
            ->assertJsonPath('data.is_revoked', true);
    });

    it('does not send email for a reusable link invite', function () {
        Notification::fake();
        $currency = Currency::factory()->mvr()->create();
        $owner = User::factory()->create(['default_currency_code' => $currency->code]);
        $group = groupWithOwnerForInvite($owner, $currency);
        $token = $owner->createToken('Owner phone');

        $this->withToken($token->plainTextToken)
            ->postJson("/api/v1/groups/{$group->id}/invites")
            ->assertCreated();

        Notification::assertNothingSent();
    });

    it('prevents members and outsiders from managing invites', function () {
        $currency = Currency::factory()->mvr()->create();
        $owner = User::factory()->create(['default_currency_code' => $currency->code]);
        $member = User::factory()->create(['default_currency_code' => $currency->code]);
        $outsider = User::factory()->create(['default_currency_code' => $currency->code]);
        $group = groupWithOwnerForInvite($owner, $currency);
        GroupMember::factory()->for($group)->for($member)->create();

        $memberToken = $member->createToken('Member phone');
        $this->withToken($memberToken->plainTextToken)
            ->postJson("/api/v1/groups/{$group->id}/invites")
            ->assertForbidden();

        app('auth')->forgetGuards();
        $outsiderToken = $outsider->createToken('Outsider phone');
        $this->withToken($outsiderToken->plainTextToken)
            ->postJson("/api/v1/groups/{$group->id}/invites")
            ->assertNotFound();
    });

    it('does not create an invite for an archived group', function () {
        $currency = Currency::factory()->mvr()->create();
        $owner = User::factory()->create(['default_currency_code' => $currency->code]);
        $group = groupWithOwnerForInvite($owner, $currency, ['archived_at' => now()]);
        $token = $owner->createToken('Owner phone');

        $this->withToken($token->plainTextToken)
            ->postJson("/api/v1/groups/{$group->id}/invites")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('group');
    });
});

describe('acceptance', function () {
    it('atomically accepts a link invite and creates membership activity', function () {
        $currency = Currency::factory()->mvr()->create();
        $owner = User::factory()->create(['default_currency_code' => $currency->code]);
        $newMember = User::factory()->create(['default_currency_code' => $currency->code]);
        $group = groupWithOwnerForInvite($owner, $currency);
        $rawToken = bin2hex(random_bytes(32));
        $invite = GroupInvite::factory()->for($group)->create([
            'invited_by' => $owner->id,
            'token_hash' => hash('sha256', $rawToken),
            'invited_email' => null,
        ]);
        $accessToken = $newMember->createToken('Member phone');

        $response = $this->withToken($accessToken->plainTextToken)
            ->postJson('/api/v1/group-invites/accept', ['token' => $rawToken]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.group_id', $group->id)
            ->assertJsonPath('data.user.id', $newMember->id);
        $membership = GroupMember::query()
            ->where('group_id', $group->id)
            ->where('user_id', $newMember->id)
            ->sole();
        expect($invite->fresh()->accepted_by)->toBe($newMember->id)
            ->and($invite->fresh()->accepted_at)->not->toBeNull();
        $this->assertDatabaseHas('activity_logs', [
            'group_id' => $group->id,
            'actor_id' => $newMember->id,
            'subject_id' => $membership->id,
            'event' => 'member.joined',
        ]);
    });

    it('accepts a targeted invite only for its matching verified email', function () {
        $currency = Currency::factory()->mvr()->create();
        $owner = User::factory()->create(['default_currency_code' => $currency->code]);
        $group = groupWithOwnerForInvite($owner, $currency);
        $rawToken = bin2hex(random_bytes(32));
        GroupInvite::factory()->for($group)->create([
            'invited_by' => $owner->id,
            'token_hash' => hash('sha256', $rawToken),
            'invited_email' => 'friend@example.com',
        ]);
        $matchingUser = User::factory()->create([
            'email' => 'Friend@Example.com',
            'default_currency_code' => $currency->code,
        ]);
        $accessToken = $matchingUser->createToken('Member phone');

        $this->withToken($accessToken->plainTextToken)
            ->postJson('/api/v1/group-invites/accept', ['token' => $rawToken])
            ->assertCreated();
    });

    it('rejects a targeted invite for a different or unverified email', function () {
        $currency = Currency::factory()->mvr()->create();
        $owner = User::factory()->create(['default_currency_code' => $currency->code]);
        $group = groupWithOwnerForInvite($owner, $currency);
        $differentUser = User::factory()->create(['default_currency_code' => $currency->code]);
        $unverifiedUser = User::factory()->unverified()->create([
            'email' => 'friend@example.com',
            'default_currency_code' => $currency->code,
        ]);

        foreach ([$differentUser, $unverifiedUser] as $index => $user) {
            $rawToken = bin2hex(random_bytes(32));
            GroupInvite::factory()->for($group)->create([
                'invited_by' => $owner->id,
                'token_hash' => hash('sha256', $rawToken),
                'invited_email' => 'friend@example.com',
            ]);
            $accessToken = $user->createToken("Member phone {$index}");

            $this->withToken($accessToken->plainTextToken)
                ->postJson('/api/v1/group-invites/accept', ['token' => $rawToken])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('invite');
            app('auth')->forgetGuards();
        }
    });

    it('rejects unknown, expired, revoked, and previously accepted tokens', function () {
        $currency = Currency::factory()->mvr()->create();
        $owner = User::factory()->create(['default_currency_code' => $currency->code]);
        $user = User::factory()->create(['default_currency_code' => $currency->code]);
        $group = groupWithOwnerForInvite($owner, $currency);
        $accessToken = $user->createToken('Member phone');

        $this->withToken($accessToken->plainTextToken)
            ->postJson('/api/v1/group-invites/accept', ['token' => bin2hex(random_bytes(32))])
            ->assertNotFound();

        foreach ([
            ['expires_at' => now()->subSecond()],
            ['revoked_at' => now()],
            ['accepted_by' => $owner->id, 'accepted_at' => now()],
        ] as $state) {
            $rawToken = bin2hex(random_bytes(32));
            GroupInvite::factory()->for($group)->create([
                'invited_by' => $owner->id,
                'token_hash' => hash('sha256', $rawToken),
                'invited_email' => null,
                ...$state,
            ]);

            $this->withToken($accessToken->plainTextToken)
                ->postJson('/api/v1/group-invites/accept', ['token' => $rawToken])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('invite');
        }
    });
});
