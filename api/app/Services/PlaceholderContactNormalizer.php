<?php

namespace App\Services;

use App\ContactType;

class PlaceholderContactNormalizer
{
    public function normalize(ContactType $type, string $value): string
    {
        $value = trim($value);

        return match ($type) {
            ContactType::Email => mb_strtolower($value),
            ContactType::Phone => preg_replace('/[\s()\-.]/', '', $value) ?? $value,
        };
    }

    public function hash(string $normalizedValue): string
    {
        return hash_hmac('sha256', $normalizedValue, (string) config('app.key'));
    }
}
