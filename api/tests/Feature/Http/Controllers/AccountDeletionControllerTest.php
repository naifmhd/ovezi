<?php

use App\Models\User;
use App\Notifications\ConfirmAccountDeletion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

it('sends a non-enumerating email confirmation without deleting on GET', function () {
    Notification::fake([ConfirmAccountDeletion::class]);
    $user = User::factory()->create(['password' => null]);
    $this->from('/delete-account')->post('/delete-account', ['email' => $user->email])->assertRedirect('/delete-account')->assertSessionHas('status');
    Notification::assertSentTo($user, ConfirmAccountDeletion::class, function ($notification) use ($user) {
        $url = $notification->toMail($user)->actionUrl;
        $this->get($url)->assertOk()->assertSee('Permanently delete account');
        $this->assertNotSoftDeleted('users', ['id' => $user->id]);
        $this->post($url, ['confirm' => '1'])->assertOk()->assertSee('Your account has been deleted');
        $this->post($url, ['confirm' => '1'])->assertGone();

        return true;
    });
    $this->assertDatabaseMissing('account_deletion_tokens', ['user_id' => $user->id]);
});

it('does not reveal unregistered addresses', function () {
    Notification::fake([ConfirmAccountDeletion::class]);
    $this->from('/delete-account')->post('/delete-account', ['email' => 'unknown@example.com'])->assertRedirect('/delete-account')->assertSessionHas('status');
    Notification::assertNothingSent();
});

it('rejects expired confirmation tokens', function () {
    $user = User::factory()->create();
    $token = str_repeat('a', 64);
    DB::table('account_deletion_tokens')->insert(['user_id' => $user->id, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->subMinute(), 'created_at' => now()->subHours(2)]);
    $this->post('/delete-account/confirm/'.$token, ['confirm' => '1'])->assertGone();
    $this->assertNotSoftDeleted('users', ['id' => $user->id]);
});
