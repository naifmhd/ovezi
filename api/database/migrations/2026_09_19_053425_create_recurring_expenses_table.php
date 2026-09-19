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
        Schema::create('recurring_expenses', function (Blueprint $table) {
            $table->id();
            $table->string('expense_type', 20);
            $table->foreignId('group_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('payer_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('payer_placeholder_id')->nullable()->constrained('placeholders')->restrictOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency_code', 3);
            $table->string('description');
            $table->string('category', 50)->nullable();
            $table->string('split_type', 20)->nullable();
            $table->string('frequency', 20);
            $table->date('start_on');
            $table->date('next_occurrence_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
            $table->index(['next_occurrence_on', 'paused_at', 'canceled_at'], 'recurring_expenses_due_index');
            $table->index(['created_by', 'canceled_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recurring_expenses');
    }
};
