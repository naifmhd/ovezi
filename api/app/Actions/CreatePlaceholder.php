<?php

namespace App\Actions;

use App\ContactType;
use App\Models\Placeholder;
use App\Models\User;
use App\Services\PlaceholderContactNormalizer;
use Illuminate\Validation\ValidationException;

class CreatePlaceholder
{
    public function __construct(private readonly PlaceholderContactNormalizer $contactNormalizer) {}

    /**
     * @param  array{name: string, contact_type: ContactType, contact_value: string}  $attributes
     */
    public function execute(User $creator, array $attributes): Placeholder
    {
        $normalizedContact = $this->contactNormalizer->normalize(
            $attributes['contact_type'],
            $attributes['contact_value'],
        );
        $contactHash = $this->contactNormalizer->hash($normalizedContact);

        if ($creator->createdPlaceholders()->where('contact_hash', $contactHash)->exists()) {
            throw ValidationException::withMessages([
                'contact_value' => 'You already have a placeholder with this contact.',
            ]);
        }

        return $creator->createdPlaceholders()->create([
            'name' => $attributes['name'],
            'contact_type' => $attributes['contact_type'],
            'contact_value' => $normalizedContact,
            'contact_hash' => $contactHash,
        ]);
    }
}
