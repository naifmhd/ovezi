<?php

namespace App\Services;

use App\Jobs\CheckExpoPushReceipts;
use App\Models\NotificationPreference;
use App\Models\PushToken;
use App\Models\User;
use App\NotificationType;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class ExpoPushService
{
    /**
     * @param  list<int>  $userIds
     * @param  array<string, int|string|null>  $data
     */
    public function sendToUsers(
        array $userIds,
        NotificationType $type,
        ?int $groupId,
        string $title,
        string $body,
        array $data,
    ): void {
        $users = User::query()
            ->whereKey($userIds)
            ->with([
                'notificationPreference',
                'pushTokens' => fn ($query) => $query->whereNull('revoked_at')->orderBy('id'),
            ])
            ->get();

        $messages = [];

        foreach ($users as $user) {
            $preference = $user->notificationPreference ?? new NotificationPreference;

            if (! $preference->allows($type) || ! $this->allowsGroupNotifications($user, $groupId)) {
                continue;
            }

            foreach ($user->pushTokens as $pushToken) {
                $messages[] = [
                    'push_token_id' => $pushToken->id,
                    'to' => $pushToken->expo_push_token,
                    'title' => $title,
                    'body' => $body,
                    'sound' => 'default',
                    'channelId' => 'default',
                    'data' => $data,
                ];
            }
        }

        foreach (array_chunk($messages, 100) as $chunk) {
            $this->sendChunk($chunk);
        }
    }

    /** @param array<string, int> $receiptTokenIds */
    public function checkReceipts(array $receiptTokenIds): void
    {
        foreach (array_chunk($receiptTokenIds, 1000, true) as $chunk) {
            $response = $this->request()
                ->post('/push/getReceipts', ['ids' => array_keys($chunk)])
                ->throw();
            $receipts = $response->json('data', []);

            foreach ($chunk as $receiptId => $pushTokenId) {
                if (data_get($receipts, "{$receiptId}.details.error") === 'DeviceNotRegistered') {
                    $this->revokeToken($pushTokenId);
                }
            }
        }
    }

    private function allowsGroupNotifications(User $user, ?int $groupId): bool
    {
        if ($groupId === null) {
            return true;
        }

        return $user->groupMemberships()
            ->where('group_id', $groupId)
            ->whereNull('left_at')
            ->whereNull('notifications_muted_at')
            ->exists();
    }

    /** @param list<array<string, mixed>> $messages */
    private function sendChunk(array $messages): void
    {
        $payload = array_map(function (array $message): array {
            unset($message['push_token_id']);

            return $message;
        }, $messages);
        $response = $this->request()->post('/push/send', $payload)->throw();
        $tickets = $response->json('data', []);
        $receiptTokenIds = [];

        foreach ($messages as $index => $message) {
            $ticket = $tickets[$index] ?? null;

            if (data_get($ticket, 'details.error') === 'DeviceNotRegistered') {
                $this->revokeToken($message['push_token_id']);

                continue;
            }

            $receiptId = data_get($ticket, 'status') === 'ok' ? data_get($ticket, 'id') : null;

            if (is_string($receiptId) && $receiptId !== '') {
                $receiptTokenIds[$receiptId] = $message['push_token_id'];
            }
        }

        if ($receiptTokenIds !== []) {
            CheckExpoPushReceipts::dispatch($receiptTokenIds)->delay(now()->addMinutes(15));
        }
    }

    private function request(): PendingRequest
    {
        $request = Http::baseUrl((string) config('services.expo.push_url'))
            ->acceptJson()
            ->asJson()
            ->connectTimeout(5)
            ->timeout(15);
        $accessToken = config('services.expo.access_token');

        return is_string($accessToken) && $accessToken !== ''
            ? $request->withToken($accessToken)
            : $request;
    }

    private function revokeToken(int $pushTokenId): void
    {
        PushToken::query()
            ->whereKey($pushTokenId)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }
}
