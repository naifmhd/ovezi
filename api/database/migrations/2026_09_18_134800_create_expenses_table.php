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
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('expense_type', 20);
            $table->foreignId('group_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('payer_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('payer_placeholder_id')->nullable()->constrained('placeholders')->restrictOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency_code', 3);
            $table->unsignedBigInteger('reporting_amount_minor');
            $table->char('reporting_currency_code', 3);
            $table->decimal('exchange_rate', 24, 12)->nullable();
            $table->string('exchange_rate_source', 20);
            $table->date('exchange_rate_effective_date')->nullable();
            $table->string('description');
            $table->string('category', 50)->nullable();
            $table->string('receipt_image_path')->nullable();
            $table->timestamp('occurred_at');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
            $table->foreign('reporting_currency_code')->references('code')->on('currencies')->restrictOnDelete();
            $table->index(['group_id', 'occurred_at']);
            $table->index(['created_by', 'expense_type', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
