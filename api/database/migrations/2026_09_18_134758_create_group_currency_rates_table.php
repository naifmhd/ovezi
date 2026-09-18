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
        Schema::create('group_currency_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->char('base_currency_code', 3);
            $table->char('quote_currency_code', 3);
            $table->decimal('rate', 24, 12);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->foreign('base_currency_code')->references('code')->on('currencies')->restrictOnDelete();
            $table->foreign('quote_currency_code')->references('code')->on('currencies')->restrictOnDelete();
            $table->unique(['group_id', 'base_currency_code', 'quote_currency_code'], 'group_currency_rates_unique_pair');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('group_currency_rates');
    }
};
