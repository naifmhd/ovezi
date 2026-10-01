<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expense_splits', function (Blueprint $table) {
            $table->unsignedBigInteger('amount_paid_minor')->nullable();
            $table->unsignedBigInteger('reporting_amount_paid_minor')->nullable();
            $table->boolean('included_in_split')->default(true);
        });
        Schema::table('recurring_expense_splits', function (Blueprint $table) {
            $table->unsignedBigInteger('amount_paid_minor')->nullable();
            $table->boolean('included_in_split')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('expense_splits', function (Blueprint $table) {
            $table->dropColumn(['amount_paid_minor', 'reporting_amount_paid_minor', 'included_in_split']);
        });
        Schema::table('recurring_expense_splits', function (Blueprint $table) {
            $table->dropColumn(['amount_paid_minor', 'included_in_split']);
        });
    }
};
