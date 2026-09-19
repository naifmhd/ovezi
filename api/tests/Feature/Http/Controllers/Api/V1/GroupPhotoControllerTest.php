<?php

use App\Models\Currency;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/** @return array{group: Group, owner: User, member: User} */
function groupForPhoto(): array
{
    $currency = Currency::factory()->mvr()->create();
    $owner = User::factory()->create(['default_currency_code' => $currency->code]);
    $member = User::factory()->create(['default_currency_code' => $currency->code]);
    $group = Group::factory()->for($owner, 'creator')->create([
        'reporting_currency_code' => $currency->code,
    ]);
    GroupMember::factory()->owner()->for($group)->for($owner)->create();
    GroupMember::factory()->for($group)->for($member)->create();

    return compact('group', 'owner', 'member');
}

it('stores a private group photo that active members can view', function () {
    ['group' => $group, 'owner' => $owner, 'member' => $member] = groupForPhoto();
    $outsider = User::factory()->create();
    Storage::fake('group_photos');
    config(['filesystems.group_photos_disk' => 'group_photos']);

    $response = $this->actingAs($owner)->postJson("/api/v1/groups/{$group->id}/photo", [
        'photo' => UploadedFile::fake()->image('trip.jpg', 1200, 1200),
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.has_photo', true)
        ->assertJsonPath('data.photo_url', fn (string $url): bool => str_starts_with(
            $url,
            route('api.v1.groups.photo.show', $group).'?v=',
        ))
        ->assertJsonCount(2, 'data.members');
    $path = $group->fresh()->photo_path;
    expect($path)->not->toBeNull();
    Storage::disk('group_photos')->assertExists($path);

    $photoResponse = $this->actingAs($member)
        ->get("/api/v1/groups/{$group->id}/photo")
        ->assertOk();
    expect($photoResponse->headers->get('cache-control'))
        ->toContain('private')
        ->toContain('max-age=3600');
    $this->actingAs($outsider)
        ->get("/api/v1/groups/{$group->id}/photo")
        ->assertNotFound();
});

it('replaces and removes a group photo without leaving old files behind', function () {
    ['group' => $group, 'owner' => $owner] = groupForPhoto();
    Storage::fake('group_photos');
    config(['filesystems.group_photos_disk' => 'group_photos']);
    $oldPath = 'group-photos/old-photo.jpg';
    Storage::disk('group_photos')->put($oldPath, 'old photo');
    $group->update(['photo_path' => $oldPath]);

    $this->actingAs($owner)->postJson("/api/v1/groups/{$group->id}/photo", [
        'photo' => UploadedFile::fake()->image('replacement.png'),
    ])->assertOk();

    $replacementPath = $group->fresh()->photo_path;
    Storage::disk('group_photos')->assertMissing($oldPath);
    Storage::disk('group_photos')->assertExists($replacementPath);

    $this->actingAs($owner)
        ->deleteJson("/api/v1/groups/{$group->id}/photo")
        ->assertNoContent();
    expect($group->fresh()->photo_path)->toBeNull();
    Storage::disk('group_photos')->assertMissing($replacementPath);
    $this->assertDatabaseHas('activity_logs', [
        'group_id' => $group->id,
        'actor_id' => $owner->id,
        'event' => 'group.updated',
    ]);
});

it('rejects invalid or oversized group photos', function () {
    ['group' => $group, 'owner' => $owner] = groupForPhoto();
    Storage::fake('group_photos');
    config(['filesystems.group_photos_disk' => 'group_photos']);

    $this->actingAs($owner)->postJson("/api/v1/groups/{$group->id}/photo", [
        'photo' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
    ])->assertUnprocessable()->assertJsonValidationErrors('photo');

    $this->actingAs($owner)->postJson("/api/v1/groups/{$group->id}/photo", [
        'photo' => UploadedFile::fake()->image('huge.jpg')->size(10_241),
    ])->assertUnprocessable()->assertJsonValidationErrors('photo');

    expect($group->fresh()->photo_path)->toBeNull();
    Storage::disk('group_photos')->assertDirectoryEmpty('/');
});

it('allows only the owner of an active group to change its photo', function () {
    ['group' => $group, 'owner' => $owner, 'member' => $member] = groupForPhoto();
    Storage::fake('group_photos');
    config(['filesystems.group_photos_disk' => 'group_photos']);

    $this->actingAs($member)->postJson("/api/v1/groups/{$group->id}/photo", [
        'photo' => UploadedFile::fake()->image('trip.jpg'),
    ])->assertForbidden();

    $group->update(['archived_at' => now()]);
    $this->actingAs($owner)->postJson("/api/v1/groups/{$group->id}/photo", [
        'photo' => UploadedFile::fake()->image('trip.jpg'),
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('group');

    expect($group->fresh()->photo_path)->toBeNull();
    Storage::disk('group_photos')->assertDirectoryEmpty('/');
});
