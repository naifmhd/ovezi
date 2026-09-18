<?php

use App\Models\Currency;
use App\Models\Expense;
use App\Models\Group;
use App\Models\GroupInvite;
use App\Models\GroupMember;
use App\Models\Placeholder;
use App\Models\SocialAccount;
use App\Models\User;

it('exports the authenticated users data without authentication secrets', function () {
    $currency = Currency::factory()->mvr()->create();
    $user = User::factory()->create([
        'name' => 'Naif',
        'email' => 'naif@example.com',
        'password' => 'export-secret-password',
        'default_currency_code' => $currency->code,
    ]);
    $outsider = User::factory()->create(['default_currency_code' => $currency->code]);
    SocialAccount::factory()->for($user)->create([
        'provider_user_id' => 'google-provider-id',
        'provider_email' => $user->email,
    ]);
    $placeholder = Placeholder::factory()->for($user, 'creator')->create([
        'name' => 'Sarah',
        'contact_value' => 'sarah@example.com',
        'contact_hash' => hash_hmac('sha256', 'sarah@example.com', config('app.key')),
    ]);
    $group = Group::factory()->for($user, 'creator')->create([
        'name' => 'Malé Weekend',
        'reporting_currency_code' => $currency->code,
    ]);
    GroupMember::factory()->owner()->for($group)->for($user)->create();
    GroupInvite::factory()->for($group)->create([
        'invited_by' => $user->id,
        'token_hash' => hash('sha256', 'private-invite-token'),
        'invited_email' => 'friend@example.com',
    ]);
    $expense = Expense::factory()->create([
        'expense_type' => 'personal',
        'group_id' => null,
        'payer_user_id' => $user->id,
        'created_by' => $user->id,
        'description' => 'My lunch',
        'currency_code' => $currency->code,
        'reporting_currency_code' => $currency->code,
    ]);
    Expense::factory()->create([
        'expense_type' => 'personal',
        'group_id' => null,
        'payer_user_id' => $outsider->id,
        'created_by' => $outsider->id,
        'description' => 'Outsider expense',
        'currency_code' => $currency->code,
        'reporting_currency_code' => $currency->code,
    ]);

    $response = $this->withToken($user->createToken('User phone')->plainTextToken)
        ->get('/api/v1/me/export');

    $response
        ->assertOk()
        ->assertHeader('content-disposition')
        ->assertJsonPath('export_version', 1)
        ->assertJsonPath('profile.email', 'naif@example.com')
        ->assertJsonPath('connected_accounts.0.provider_user_id', 'google-provider-id')
        ->assertJsonPath('created_placeholders.0.id', $placeholder->id)
        ->assertJsonPath('created_placeholders.0.contact_value', 'sarah@example.com')
        ->assertJsonPath('group_memberships.0.group.name', 'Malé Weekend')
        ->assertJsonPath('created_group_invitations.0.invited_email', 'friend@example.com')
        ->assertJsonPath('expenses.0.id', $expense->id);

    expect($response->json('expenses'))->toHaveCount(1);
    expect($response->getContent())
        ->not->toContain('export-secret-password')
        ->not->toContain('private-invite-token')
        ->not->toContain('token_hash')
        ->not->toContain('contact_hash')
        ->not->toContain('Outsider expense');
});

it('requires authentication to export personal data', function () {
    $this->getJson('/api/v1/me/export')->assertUnauthorized();
});
