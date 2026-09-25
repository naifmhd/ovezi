<?php

namespace App\Http\Controllers;

use App\Actions\DeleteAccount;
use App\Models\User;
use App\Notifications\ConfirmAccountDeletion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AccountDeletionController extends Controller
{
    public function create(): View
    {
        return view('account-deletion');
    }

    public function store(Request $request): RedirectResponse
    {
        $input = $request->validate(['email' => ['required', 'email:rfc', 'max:255']]);
        $email = mb_strtolower(trim($input['email']));
        $key = 'deletion-mail:'.hash('sha256', $email);
        if (! RateLimiter::tooManyAttempts($key, 1)) {
            RateLimiter::hit($key, 300);
            $user = User::query()->where('email', $email)->first();
            if ($user) {
                $token = Str::random(64);
                DB::table('account_deletion_tokens')->updateOrInsert(['user_id' => $user->id], [
                    'token_hash' => hash('sha256', $token), 'expires_at' => now()->addHour(), 'created_at' => now(),
                ]);
                $user->notify(new ConfirmAccountDeletion($token));
            }
        }

        return back()->with('status', 'If an account matches that email, a confirmation link will arrive shortly. The link expires in one hour.');
    }

    public function show(string $token): View
    {
        $this->findToken($token);

        return view('account-deletion', ['confirmationToken' => $token]);
    }

    public function destroy(Request $request, string $token, DeleteAccount $delete): View
    {
        $request->validate(['confirm' => ['accepted']]);
        DB::transaction(function () use ($token, $delete): void {
            $record = $this->findToken($token);
            $user = User::query()->lockForUpdate()->findOrFail($record->user_id);
            $this->findToken($token);
            $delete->deleteVerified($user);
        }, 3);

        return view('account-deletion', ['completed' => true]);
    }

    private function findToken(string $token): object
    {
        abort_unless(strlen($token) === 64, 404);
        $record = DB::table('account_deletion_tokens')->where('token_hash', hash('sha256', $token))->where('expires_at', '>', now())->first();
        abort_unless($record, 410, 'This confirmation link has expired or has already been used. Request a new link from the account deletion page.');

        return $record;
    }
}
