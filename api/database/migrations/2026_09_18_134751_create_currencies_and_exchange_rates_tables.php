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
        Schema::create('currencies', function (Blueprint $table) {
            $table->char('code', 3)->primary();
            $table->string('name');
            $table->char('numeric_code', 3)->nullable()->unique();
            $table->string('symbol', 16)->nullable();
            $table->unsignedInteger('minor_unit_factor')->default(100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->char('base_currency_code', 3);
            $table->char('quote_currency_code', 3);
            $table->decimal('rate', 24, 12);
            $table->string('provider', 50);
            $table->date('effective_date');
            $table->timestamp('fetched_at');
            $table->timestamps();

            $table->foreign('base_currency_code')->references('code')->on('currencies')->restrictOnDelete();
            $table->foreign('quote_currency_code')->references('code')->on('currencies')->restrictOnDelete();
            $table->unique(
                ['base_currency_code', 'quote_currency_code', 'provider', 'effective_date'],
                'exchange_rates_unique_snapshot',
            );
            $table->index(['quote_currency_code', 'effective_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
        Schema::dropIfExists('currencies');
    }
};
