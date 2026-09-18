<?php

namespace App\Http\Requests\Api\V1;

use App\ExpenseType;
use App\Models\Expense;
use App\Models\Group;
use App\SplitType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
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
        $splitType = $this->input('split_type');
        $splitValueMinimum = in_array($splitType, [SplitType::Percentage->value, SplitType::Shares->value], true)
            ? 1
            : 0;

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
            'participants.*.user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->whereNull('deleted_at'),
            ],
            'participants.*.placeholder_id' => ['nullable', 'integer', 'exists:placeholders,id'],
            'participants.*.value' => [
                Rule::requiredIf($splitType !== SplitType::Equal->value),
                'nullable',
                'integer',
                "min:{$splitValueMinimum}",
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
            if ($validator->errors()->hasAny([
                'expense_type',
                'payer_user_id',
                'payer_placeholder_id',
                'amount_minor',
                'split_type',
                'participants',
            ])) {
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

            $participantKeys = [];
            $splitTotal = 0;
            $canValidateSplitTotal = true;

            foreach ($this->input('participants', []) as $index => $participant) {
                if (! is_array($participant)) {
                    $canValidateSplitTotal = false;

                    continue;
                }

                $participantKey = $this->participantKey(
                    $participant['user_id'] ?? null,
                    $participant['placeholder_id'] ?? null,
                );

                if ($participantKey === null) {
                    $validator->errors()->add(
                        "participants.{$index}.user_id",
                        'Select exactly one user or placeholder for each participant.',
                    );

                    $canValidateSplitTotal = false;

                    continue;
                }

                if (in_array($participantKey, $participantKeys, true)) {
                    $validator->errors()->add('participants', 'Each participant may only appear once.');
                }

                $participantKeys[] = $participantKey;

                if (isset($participant['value'])) {
                    $validatedInteger = filter_var($participant['value'], FILTER_VALIDATE_INT);

                    if ($validatedInteger === false) {
                        $canValidateSplitTotal = false;
                    } else {
                        $splitTotal += $validatedInteger;
                    }
                }
            }

            if (! in_array($payerKey, $participantKeys, true)) {
                $validator->errors()->add('participants', 'The payer must be included in the split.');
            }

            if ($canValidateSplitTotal
                && $this->input('split_type') === SplitType::Exact->value
                && $splitTotal !== $this->integer('amount_minor')) {
                $validator->errors()->add('participants', 'Exact split amounts must equal the expense amount.');
            }

            if ($canValidateSplitTotal
                && $this->input('split_type') === SplitType::Percentage->value
                && $splitTotal !== 10_000) {
                $validator->errors()->add('participants', 'Percentage splits must total 100.00%.');
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
