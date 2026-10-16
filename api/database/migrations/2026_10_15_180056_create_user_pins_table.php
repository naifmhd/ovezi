<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_pins', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('group_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('lending_contact_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unique(['user_id', 'group_id']);
            $table->unique(['user_id', 'lending_contact_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_pins');
    }
};
