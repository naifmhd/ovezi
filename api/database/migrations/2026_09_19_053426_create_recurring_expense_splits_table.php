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
        Schema::create('recurring_expense_splits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recurring_expense_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('placeholder_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('split_value', 20, 8)->nullable();
            $table->timestamps();

            $table->unique(['recurring_expense_id', 'user_id'], 'recurring_splits_user_unique');
            $table->unique(['recurring_expense_id', 'placeholder_id'], 'recurring_splits_placeholder_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recurring_expense_splits');
    }
};
