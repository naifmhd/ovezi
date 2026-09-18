<?php

namespace App\Observers;

use App\Events\DomainChanged;
use App\Models\Expense;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Settlement;
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

    public function deleted(Model $model): void
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
            default => null,
        };

        if ($resource === null) {
            return;
        }

        DomainChanged::dispatch(
            $resource,
            $action,
            (int) $model->getKey(),
            $model instanceof Group ? (int) $model->getKey() : $this->groupId($model),
        );
    }

    private function groupId(Model $model): ?int
    {
        $groupId = $model->getAttribute('group_id');

        return $groupId === null ? null : (int) $groupId;
    }
}
