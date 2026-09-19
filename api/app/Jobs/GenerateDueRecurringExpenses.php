<?php

namespace App\Jobs;

use App\Models\RecurringExpense;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateDueRecurringExpenses implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 3600;

    public function handle(): void
    {
        RecurringExpense::query()
            ->whereNull('paused_at')
            ->whereNull('canceled_at')
            ->whereNotNull('next_occurrence_on')
            ->whereDate('next_occurrence_on', '<=', today())
            ->pluck('id')
            ->each(fn (int $id) => GenerateRecurringExpenseOccurrences::dispatch($id));
    }
}
