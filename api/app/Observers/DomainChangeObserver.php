<?php

namespace App\Observers;

use App\Events\DomainChanged;
use App\Models\Expense;
use App\Models\Friendship;
use App\Models\Group;
use App\Models\GroupCurrencyRate;
use App\Models\GroupInvite;
use App\Models\GroupMember;
use App\Models\Placeholder;
use App\Models\RecurringExpense;
use App\Models\Settlement;
use App\Services\RealtimeAudience;
use Illuminate\Database\Eloquent\Model;

class DomainChangeObserver
{
    public function created(Model $model): void
    {
        $this->dispatch($model, 'created');
    }

    public function updated(Model $model): void
    {
        $this->dispatch($model, 'updated');
    }

    public function deleting(Model $model): void
    {
        $this->dispatch($model, 'deleted');
    }

    public function restored(Model $model): void
    {
        $this->dispatch($model, 'restored');
    }

    private function dispatch(Model $model, string $action): void
    {
        $resource = match ($model::class) {
            Expense::class => 'expense',
            Settlement::class => 'settlement',
            Group::class => 'group',
            GroupMember::class => 'group_member',
            Friendship::class => 'friendship',
            GroupInvite::class => 'group_invite',
            Placeholder::class => 'placeholder',
            GroupCurrencyRate::class => 'group_currency_rate',
            RecurringExpense::class => 'recurring_expense',
            default => null,
        };

        if ($resource === null) {
            return;
        }

        $resourceId = (int) $model->getKey();
        DomainChanged::dispatch(
            $resource,
            $action,
            $resourceId,
            $model instanceof Group ? (int) $model->getKey() : $this->groupId($model),
            app(RealtimeAudience::class)->for($resource, $resourceId),
        );
    }

    private function groupId(Model $model): ?int
    {
        $groupId = $model->getAttribute('group_id');

        return $groupId === null ? null : (int) $groupId;
    }
}
