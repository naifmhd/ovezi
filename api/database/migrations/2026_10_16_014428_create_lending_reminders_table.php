<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lending_reminders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lending_loan_id')->constrained()->cascadeOnDelete();
            $table->timestamp('remind_at')->index();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'lending_loan_id']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('lending_reminders');
    }
};
