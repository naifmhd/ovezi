<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('recurring_expense_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->restrictOnDelete();
            $table->date('recurring_occurrence_on')->nullable()->after('recurring_expense_id');
            $table->unique(
                ['recurring_expense_id', 'recurring_occurrence_on'],
                'expenses_recurring_occurrence_unique',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['recurring_expense_id']);
            $table->dropUnique('expenses_recurring_occurrence_unique');
            $table->dropColumn('recurring_expense_id');
            $table->dropColumn('recurring_occurrence_on');
        });
    }
};
