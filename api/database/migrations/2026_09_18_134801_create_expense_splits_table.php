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
        Schema::create('expense_splits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('placeholder_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount_owed_minor');
            $table->string('split_type', 20);
            $table->decimal('split_value', 20, 8)->nullable();
            $table->timestamps();

            $table->unique(['expense_id', 'user_id']);
            $table->unique(['expense_id', 'placeholder_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expense_splits');
    }
};
