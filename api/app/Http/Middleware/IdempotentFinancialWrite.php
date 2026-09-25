<?php

namespace App\Http\Middleware;

use App\Models\Friendship;
use App\Models\Group;
use App\Models\Placeholder;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class IdempotentFinancialWrite
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('POST') || ! $request->routeIs(
            'api.v1.expenses.store', 'api.v1.settlements.store',
            'api.v1.groups.settlements.store', 'api.v1.recurring-expenses.store',
        )) {
            return $next($request);
        }
        $key = $request->header('Idempotency-Key');
        // Older clients still get balance locking; new clients also get durable replay.
        abort_unless($key === null || Str::isUuid($key), 422, 'A valid submission identifier is required.');
        $fingerprint = hash('sha256', $request->path().'|'.json_encode($this->canonical($request->all()), JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($request, $next, $key, $fingerprint): Response {
            // Serializes same-account retries even before the first submission row exists.
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            // Acquire the shared balance lock before any snapshot reads. This also
            // serializes settlements submitted by different members at once.
            $groupId = $request->route('group')?->id ?? $request->input('group_id');
            if ($groupId) {
                Group::query()->lockForUpdate()->findOrFail($groupId);
            } elseif ($request->routeIs('api.v1.settlements.store')) {
                $userIds = collect([$request->input('from_user_id'), $request->input('to_user_id')])->filter()->map(fn ($id) => (int) $id)->sort()->values();
                if ($userIds->count() === 2) {
                    Friendship::query()->where('user_id', $userIds[0])->where('friend_id', $userIds[1])->lockForUpdate()->first();
                } else {
                    Placeholder::query()->whereKey($request->input('from_placeholder_id') ?? $request->input('to_placeholder_id'))->lockForUpdate()->first();
                }
            }
            if ($key === null) {
                return $next($request);
            }
            $saved = DB::table('financial_submissions')->where('user_id', $user->id)->where('submission_key', $key)->first();
            if ($saved !== null) {
                $groupId = $request->route('group')?->id ?? $request->input('group_id');
                if ($groupId) {
                    Gate::authorize('view', Group::query()->findOrFail($groupId));
                }
                abort_unless(hash_equals($saved->fingerprint, $fingerprint), 409, 'This submission identifier was already used for different details.');

                abort_if($saved->response_body === '', 410, 'This submission was already saved. Open your history to review it.');

                return response(json_encode($this->identityNames(json_decode(Crypt::decryptString($saved->response_body), true, 512, JSON_THROW_ON_ERROR)), JSON_THROW_ON_ERROR), $saved->response_status)
                    ->header('Content-Type', 'application/json')->header('Idempotency-Replayed', 'true');
            }
            $response = $next($request);
            if ($response->isSuccessful()) {
                DB::table('financial_submissions')->insert([
                    'user_id' => $user->id, 'submission_key' => $key, 'fingerprint' => $fingerprint,
                    'response_status' => $response->getStatusCode(),
                    'response_body' => Crypt::encryptString(json_encode($this->identityNames(json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR), true), JSON_THROW_ON_ERROR)), 'created_at' => now(),
                ]);
            }

            return $response;
        }, 3);
    }

    /** Store no participant names in the replay cache; resolve current anonymized names on replay. */
    private function identityNames(array $input, bool $strip = false): array
    {
        if (array_key_exists('name', $input) && (array_key_exists('user_id', $input) || array_key_exists('placeholder_id', $input))) {
            $input['name'] = $strip ? null : (isset($input['user_id'])
                ? User::withTrashed()->find($input['user_id'])?->name
                : Placeholder::query()->find($input['placeholder_id'] ?? null)?->name);
        }
        foreach ($input as $key => $value) {
            if (is_array($value)) {
                $input[$key] = $this->identityNames($value, $strip);
            }
        }

        return $input;
    }

    private function canonical(array $input): array
    {
        if (! array_is_list($input)) {
            ksort($input);
        }
        foreach ($input as $key => $value) {
            if (is_array($value)) {
                $input[$key] = $this->canonical($value);
            }
        }

        return $input;
    }
}
