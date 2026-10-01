<?php

namespace App\Http\Requests\Api\V1;

use App\Exceptions\InvalidSplit;
use App\ExpenseType;
use App\Models\Expense;
use App\Models\Group;
use App\Services\ExpenseAllocation;
use App\SplitType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

class StoreExpenseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null || ! $user->can('create', Expense::class)) {
            return false;
        }

        if ($this->input('expense_type') !== ExpenseType::Group->value || ! $this->filled('group_id')) {
            return true;
        }

        $group = Group::query()->find($this->integer('group_id'));

        return $group === null || $user->can('view', $group);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $expenseType = $this->input('expense_type');

        return [
            'expense_type' => ['required', Rule::enum(ExpenseType::class)],
            'group_id' => [
                Rule::requiredIf($expenseType === ExpenseType::Group->value),
                Rule::prohibitedIf($expenseType !== ExpenseType::Group->value),
                'integer',
                'exists:groups,id',
            ],
            'payer_user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->whereNull('deleted_at'),
            ],
            'payer_placeholder_id' => ['nullable', 'integer', 'exists:placeholders,id'],
            'amount_minor' => ['required', 'integer', 'min:1'],
            'currency_code' => ['required', 'string', 'size:3', 'exists:currencies,code'],
            'description' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:50'],
            'occurred_at' => ['required', 'date'],
            'split_type' => [
                Rule::requiredIf($expenseType !== ExpenseType::Personal->value),
                Rule::prohibitedIf($expenseType === ExpenseType::Personal->value),
                'nullable',
                Rule::enum(SplitType::class),
            ],
            'participants' => [
                Rule::requiredIf($expenseType !== ExpenseType::Personal->value),
                Rule::prohibitedIf($expenseType === ExpenseType::Personal->value),
                'array',
                'min:1',
            ],
            'participants.*' => ['array:user_id,placeholder_id,value,amount_paid_minor,included_in_split'],
            'participants.*.user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->whereNull('deleted_at'),
            ],
            'participants.*.placeholder_id' => ['nullable', 'integer', 'exists:placeholders,id'],
            'participants.*.included_in_split' => ['sometimes', 'boolean'],
            'participants.*.amount_paid_minor' => ['nullable', 'integer', 'min:0', 'max:'.PHP_INT_MAX],
            'participants.*.value' => [
                'nullable',
                'integer',
                'min:0',
            ],
            'expense_rate' => [
                'nullable',
                'string',
                'numeric',
                'gt:0',
                'regex:/^\d{1,12}(\.\d{1,12})?$/',
            ],
            'confirmed_duplicate' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $payerKey = $this->participantKey(
                $this->input('payer_user_id'),
                $this->input('payer_placeholder_id'),
            );

            if ($payerKey === null) {
                $validator->errors()->add('payer_user_id', 'Select exactly one payer.');

                return;
            }

            if ($this->input('expense_type') === ExpenseType::Personal->value) {
                if ((int) $this->input('payer_user_id') !== $this->user()?->id) {
                    $validator->errors()->add('payer_user_id', 'A personal expense must be paid by you.');
                }

                return;
            }

            try {
                app(ExpenseAllocation::class)->calculate(
                    $this->integer('amount_minor'), $this->integer('amount_minor'),
                    SplitType::from($this->input('split_type')),
                    array_map(fn (array $participant): array => [
                        'user_id' => isset($participant['user_id']) ? (int) $participant['user_id'] : null,
                        'placeholder_id' => isset($participant['placeholder_id']) ? (int) $participant['placeholder_id'] : null,
                        'included_in_split' => (bool) ($participant['included_in_split'] ?? true),
                        ...(isset($participant['value']) ? ['value' => (int) $participant['value']] : []),
                        ...(isset($participant['amount_paid_minor']) ? ['amount_paid_minor' => (int) $participant['amount_paid_minor']] : []),
                    ], $this->input('participants', [])),
                    $this->filled('payer_user_id') ? $this->integer('payer_user_id') : null,
                    $this->filled('payer_placeholder_id') ? $this->integer('payer_placeholder_id') : null,
                );
            } catch (InvalidSplit $exception) {
                $validator->errors()->add('participants', $exception->getMessage());
            } catch (ValidationException $exception) {
                foreach ($exception->errors() as $key => $messages) {
                    foreach ($messages as $message) {
                        $validator->errors()->add($key, $message);
                    }
                }
            }

        }];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'expense_type' => $this->string('expense_type')->lower()->toString(),
            'currency_code' => $this->string('currency_code')->upper()->toString(),
            'description' => $this->string('description')->trim()->toString(),
            'category' => $this->filled('category')
                ? $this->string('category')->trim()->lower()->toString()
                : null,
            'split_type' => $this->filled('split_type')
                ? $this->string('split_type')->lower()->toString()
                : null,
        ]);
    }

    private function participantKey(mixed $userId, mixed $placeholderId): ?string
    {
        if (($userId === null) === ($placeholderId === null)) {
            return null;
        }

        return $userId !== null ? "user:{$userId}" : "placeholder:{$placeholderId}";
    }
}
