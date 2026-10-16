<?php

namespace App\Http\Middleware;

use App\Models\Expense;
use App\Models\Group;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class LockGroupWrite
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe()) {
            return $next($request);
        }
        $routeGroup = $request->route('group');
        $groupId = $routeGroup instanceof Group ? $routeGroup->id : $routeGroup;
        $expense = $request->route('expense');
        if ($expense && ! $expense instanceof Expense) {
            $expense = Expense::withTrashed()->find($expense);
        }
        $groupId ??= $expense?->group_id ?? $request->route('recurringExpense')?->group_id;
        if ($request->routeIs('api.v1.expenses.store', 'api.v1.recurring-expenses.store')) {
            $groupId ??= $request->input('group_id');
        }
        if (! $groupId) {
            return $next($request);
        }

        return DB::transaction(function () use ($request, $next, $groupId, $routeGroup, $expense): Response {
            $group = Group::withTrashed()->lockForUpdate()->findOrFail($groupId);
            abort_if($group->trashed() && ! $request->routeIs('api.v1.groups.restore'), 404);
            if ($routeGroup instanceof Group) {
                $request->route()->setParameter('group', $group);
            }
            $expense?->unsetRelation('group');

            return $next($request);
        }, 3);
    }
}
