<?php

namespace App\Http\Requests\Api\V1;

class StoreQuickLendingLoanRequest extends StoreLendingLoanRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'contact_id' => ['required_without:person', 'prohibits:person', 'integer', 'min:1'],
            'person' => ['required_without:contact_id', 'prohibits:contact_id', 'array:name,email'],
            'person.name' => ['required_with:person', 'string', 'max:120'],
            'person.email' => ['nullable', 'email:rfc', 'max:254'],
        ];
    }
}
