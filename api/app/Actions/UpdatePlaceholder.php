<?php

namespace App\Actions;

use App\ContactType;
use App\Models\Placeholder;
use App\Models\User;
use App\Services\PlaceholderContactNormalizer;
use Illuminate\Validation\ValidationException;

class UpdatePlaceholder
{
    public function __construct(private readonly PlaceholderContactNormalizer $contactNormalizer) {}

    /**
     * @param  array{name?: string, contact_type?: ContactType, contact_value?: string}  $attributes
     */
    public function execute(User $creator, Placeholder $placeholder, array $attributes): Placeholder
    {
        if ($placeholder->claimed_by !== null) {
            throw ValidationException::withMessages([
                'placeholder' => 'A claimed placeholder can no longer be changed.',
            ]);
        }

        if (isset($attributes['contact_type'], $attributes['contact_value'])) {
            $normalizedContact = $this->contactNormalizer->normalize(
                $attributes['contact_type'],
                $attributes['contact_value'],
            );
            $contactHash = $this->contactNormalizer->hash($normalizedContact);

            if ($creator->createdPlaceholders()
                ->whereKeyNot($placeholder->id)
                ->where('contact_hash', $contactHash)
                ->exists()) {
                throw ValidationException::withMessages([
                    'contact_value' => 'You already have a placeholder with this contact.',
                ]);
            }

            $attributes['contact_value'] = $normalizedContact;
            $attributes['contact_hash'] = $contactHash;
        }

        $placeholder->update($attributes);

        return $placeholder->refresh();
    }
}
