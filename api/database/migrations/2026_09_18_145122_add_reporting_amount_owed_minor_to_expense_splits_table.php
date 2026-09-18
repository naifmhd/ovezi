<?php

use Brick\Math\BigInteger;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('expense_splits', function (Blueprint $table) {
            $table->unsignedBigInteger('reporting_amount_owed_minor')
                ->nullable()
                ->after('amount_owed_minor');
        });

        DB::table('expenses')->orderBy('id')->eachById(function (object $expense): void {
            $splits = DB::table('expense_splits')
                ->where('expense_id', $expense->id)
                ->orderBy('id')
                ->get();

            if ($splits->isEmpty()) {
                return;
            }

            $baseTotal = $splits->reduce(
                fn (BigInteger $total, object $split): BigInteger => $total->plus($split->amount_owed_minor),
                BigInteger::zero(),
            );
            $allocatedToOthers = 0;
            $payerSplitId = null;

            foreach ($splits as $split) {
                $isPayer = ($expense->payer_user_id !== null && $split->user_id === $expense->payer_user_id)
                    || ($expense->payer_placeholder_id !== null && $split->placeholder_id === $expense->payer_placeholder_id);

                if ($isPayer) {
                    $payerSplitId = $split->id;

                    continue;
                }

                $reportingAmount = BigInteger::of($expense->reporting_amount_minor)
                    ->multipliedBy($split->amount_owed_minor)
                    ->quotient($baseTotal)
                    ->toInt();
                $allocatedToOthers += $reportingAmount;

                DB::table('expense_splits')->where('id', $split->id)->update([
                    'reporting_amount_owed_minor' => $reportingAmount,
                ]);
            }

            if ($payerSplitId === null) {
                throw new RuntimeException("Expense {$expense->id} does not include its payer in the splits.");
            }

            DB::table('expense_splits')->where('id', $payerSplitId)->update([
                'reporting_amount_owed_minor' => $expense->reporting_amount_minor - $allocatedToOthers,
            ]);
        });

        Schema::table('expense_splits', function (Blueprint $table) {
            $table->unsignedBigInteger('reporting_amount_owed_minor')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expense_splits', function (Blueprint $table) {
            $table->dropColumn('reporting_amount_owed_minor');
        });
    }
};
