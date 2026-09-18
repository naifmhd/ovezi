<?php

use App\ContactType;
use App\Models\Placeholder;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('encrypts contact details and hides them from serialization', function () {
    $placeholder = Placeholder::factory()->create([
        'contact_type' => ContactType::Email,
        'contact_value' => 'sarah@example.com',
        'contact_hash' => hash_hmac('sha256', 'sarah@example.com', config('app.key')),
    ]);

    $storedValue = DB::table('placeholders')->where('id', $placeholder->id)->value('contact_value');

    expect($storedValue)->not->toBe('sarah@example.com');
    expect($placeholder->contact_value)->toBe('sarah@example.com');
    expect($placeholder->toArray())->not->toHaveKeys(['contact_value', 'contact_hash']);
});

it('rejects duplicate contacts owned by the same creator', function () {
    $creator = User::factory()->create();
    $contactHash = hash_hmac('sha256', 'sarah@example.com', config('app.key'));
    Placeholder::factory()->for($creator, 'creator')->create([
        'contact_hash' => $contactHash,
    ]);

    expect(fn () => Placeholder::factory()->for($creator, 'creator')->create([
        'contact_hash' => $contactHash,
    ]))->toThrow(QueryException::class);
});
