<?php

use App\Models\Settlement;
use App\Models\User;

it('records a settlement between different participants', function () {
    $settlement = Settlement::factory()->create();

    $this->assertModelExists($settlement);
    expect($settlement->from_user_id)->not->toBe($settlement->to_user_id);
    expect($settlement->amount_minor)->toBe(1500);
});

it('rejects a settlement paid to the same participant', function () {
    $user = User::factory()->create();

    expect(fn () => Settlement::factory()->create([
        'from_user_id' => $user->id,
        'to_user_id' => $user->id,
    ]))->toThrow(InvalidArgumentException::class, 'sender and recipient must be different');
});
