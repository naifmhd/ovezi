<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExpenseCategoryController extends Controller
{
    private const DEFAULTS = ['Food', 'Transport', 'Home', 'Travel', 'Other'];

    public function index(Request $request): JsonResponse
    {
        $custom = DB::table('expense_categories')->where('user_id', $request->user()->id)->orderBy('name')->get(['id', 'name', 'archived_at']);
        $recent = Expense::query()->where('created_by', $request->user()->id)->whereNotNull('category')->selectRaw('category, MAX(created_at) as last_used')->groupBy('category')->orderByDesc('last_used')->limit(10)->pluck('category');

        return response()->json(['data' => collect(self::DEFAULTS)->map(fn (string $name) => ['id' => null, 'name' => $name, 'archived_at' => null])->concat($custom), 'recent' => $recent]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        return DB::transaction(function () use ($request, $data): JsonResponse {
            User::query()->lockForUpdate()->findOrFail($request->user()->id);
            $this->uniqueName($request, $data['name']);
            if (DB::table('expense_categories')->where('user_id', $request->user()->id)->count() >= 100) {
                throw ValidationException::withMessages(['name' => 'You can save up to 100 custom categories. Rename or restore an existing category.']);
            }
            $id = DB::table('expense_categories')->insertGetId(['user_id' => $request->user()->id, 'name' => $data['name'], 'normalized_name' => mb_strtolower($data['name']), 'created_at' => now(), 'updated_at' => now()]);

            return response()->json(['data' => ['id' => $id, 'name' => $data['name'], 'archived_at' => null]], 201);
        }, 3);
    }

    public function update(Request $request, int $category): JsonResponse
    {
        $data = $this->validated($request, true);

        return DB::transaction(function () use ($request, $category, $data): JsonResponse {
            User::query()->lockForUpdate()->findOrFail($request->user()->id);
            $row = DB::table('expense_categories')->where('user_id', $request->user()->id)->where('id', $category)->first();
            abort_unless($row, 404);
            $changes = ['updated_at' => now()];
            if (isset($data['name'])) {
                $this->uniqueName($request, $data['name'], $category);
                $changes += ['name' => $data['name'], 'normalized_name' => mb_strtolower($data['name'])];
            }
            if (array_key_exists('archived', $data)) {
                $changes['archived_at'] = $data['archived'] ? now() : null;
            }
            DB::table('expense_categories')->where('id', $category)->update($changes);

            return response()->json(['data' => DB::table('expense_categories')->where('id', $category)->first(['id', 'name', 'archived_at'])]);
        }, 3);
    }

    private function validated(Request $request, bool $updating = false): array
    {
        if ($request->has('name') && is_string($request->input('name'))) {
            $request->merge(['name' => preg_replace('/\s+/u', ' ', trim($request->input('name')))]);
        }

        return $request->validate(['name' => [$updating ? 'sometimes' : 'required', 'required', 'string', 'max:50'], 'archived' => ['sometimes', 'boolean']]);
    }

    private function uniqueName(Request $request, string $name, ?int $except = null): void
    {
        $normalized = mb_strtolower($name);
        if (in_array($normalized, array_map(mb_strtolower(...), self::DEFAULTS)) || DB::table('expense_categories')->where('user_id', $request->user()->id)->where('normalized_name', $normalized)->when($except, fn ($query) => $query->where('id', '!=', $except))->exists()) {
            throw ValidationException::withMessages(['name' => 'This category already exists. Select it or restore it from Manage categories.']);
        }
    }
}
