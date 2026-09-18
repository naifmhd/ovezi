<?php

use App\ContactType;
use App\Models\Placeholder;
use App\Models\User;

describe('index', function () {
    it('requires authentication', function () {
        $this->getJson('/api/v1/placeholders')->assertUnauthorized();
    });

    it('lists only placeholders created by the authenticated user without private contact data', function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $owned = Placeholder::factory()->for($user, 'creator')->create(['name' => 'Owned guest']);
        Placeholder::factory()->for($otherUser, 'creator')->create(['name' => 'Other guest']);
        $token = $user->createToken('User phone');

        $response = $this->withToken($token->plainTextToken)->getJson('/api/v1/placeholders');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $owned->id)
            ->assertJsonPath('data.0.name', 'Owned guest')
            ->assertJsonMissingPath('data.0.contact_value')
            ->assertJsonMissingPath('data.0.contact_hash');
    });
});

describe('store', function () {
    it('creates an email placeholder with normalized encrypted contact data', function () {
        $user = User::factory()->create();
        $token = $user->createToken('User phone');

        $response = $this->withToken($token->plainTextToken)->postJson('/api/v1/placeholders', [
            'name' => ' Sarah ',
            'contact_type' => 'EMAIL',
            'contact_value' => ' Sarah@Example.COM ',
            'created_by' => User::factory()->create()->id,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.name', 'Sarah')
            ->assertJsonPath('data.contact_type', ContactType::Email->value)
            ->assertJsonPath('data.is_claimed', false)
            ->assertJsonMissingPath('data.contact_value')
            ->assertJsonMissingPath('data.contact_hash');

        $placeholder = Placeholder::query()->sole();
        expect($placeholder->created_by)->toBe($user->id)
            ->and($placeholder->contact_value)->toBe('sarah@example.com')
            ->and($placeholder->contact_hash)->toBe(
                hash_hmac('sha256', 'sarah@example.com', config('app.key')),
            );
    });

    it('normalizes and validates international phone numbers', function () {
        $user = User::factory()->create();
        $token = $user->createToken('User phone');

        $response = $this->withToken($token->plainTextToken)->postJson('/api/v1/placeholders', [
            'name' => 'Aisha',
            'contact_type' => 'phone',
            'contact_value' => '+960 777-1234',
        ]);

        $response->assertCreated()->assertJsonPath('data.contact_type', 'phone');
        expect(Placeholder::query()->sole()->contact_value)->toBe('+9607771234');

        $this->withToken($token->plainTextToken)->postJson('/api/v1/placeholders', [
            'name' => 'Invalid phone',
            'contact_type' => 'phone',
            'contact_value' => '7771234',
        ])->assertUnprocessable()->assertJsonValidationErrors('contact_value');
    });

    it('rejects a duplicate normalized contact for the same creator', function () {
        $user = User::factory()->create();
        $token = $user->createToken('User phone');
        Placeholder::factory()->for($user, 'creator')->create([
            'contact_value' => 'friend@example.com',
            'contact_hash' => hash_hmac('sha256', 'friend@example.com', config('app.key')),
        ]);

        $this->withToken($token->plainTextToken)->postJson('/api/v1/placeholders', [
            'name' => 'Duplicate',
            'contact_type' => 'email',
            'contact_value' => 'FRIEND@example.com',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('contact_value');

        $this->assertDatabaseCount('placeholders', 1);
    });
});

describe('update', function () {
    it('lets the creator correct an unclaimed placeholder', function () {
        $user = User::factory()->create();
        $placeholder = Placeholder::factory()->for($user, 'creator')->create();
        $token = $user->createToken('User phone');

        $response = $this->withToken($token->plainTextToken)
            ->patchJson("/api/v1/placeholders/{$placeholder->id}", [
                'name' => ' Corrected name ',
                'contact_type' => 'phone',
                'contact_value' => '+960 700-0000',
                'claimed_by' => User::factory()->create()->id,
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.name', 'Corrected name')
            ->assertJsonPath('data.contact_type', 'phone')
            ->assertJsonPath('data.is_claimed', false);
        expect($placeholder->fresh()->contact_value)->toBe('+9607000000');
    });

    it('returns 404 when another user tries to update a placeholder', function () {
        $creator = User::factory()->create();
        $otherUser = User::factory()->create();
        $placeholder = Placeholder::factory()->for($creator, 'creator')->create();
        $token = $otherUser->createToken('Other phone');

        $this->withToken($token->plainTextToken)
            ->patchJson("/api/v1/placeholders/{$placeholder->id}", ['name' => 'Changed'])
            ->assertNotFound();
    });

    it('does not allow a claimed placeholder to be changed', function () {
        $creator = User::factory()->create();
        $claimedBy = User::factory()->create();
        $placeholder = Placeholder::factory()->for($creator, 'creator')->create([
            'claimed_by' => $claimedBy->id,
            'claimed_at' => now(),
        ]);
        $token = $creator->createToken('Creator phone');

        $this->withToken($token->plainTextToken)
            ->patchJson("/api/v1/placeholders/{$placeholder->id}", ['name' => 'Changed'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('placeholder');

        expect($placeholder->fresh()->name)->not->toBe('Changed');
    });
});
