<?php

namespace App\Http\Requests\Api\V1;

use App\RecurrenceFrequency;
use Illuminate\Validation\Rule;

class StoreRecurringExpenseRequest extends StoreExpenseRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'frequency' => ['required', Rule::enum(RecurrenceFrequency::class)],
            'ends_on' => ['nullable', 'date', 'after:occurred_at'],
        ];
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $this->merge([
            'frequency' => $this->string('frequency')->trim()->lower()->toString(),
            'ends_on' => $this->filled('ends_on')
                ? $this->string('ends_on')->trim()->toString()
                : null,
        ]);
    }
}
