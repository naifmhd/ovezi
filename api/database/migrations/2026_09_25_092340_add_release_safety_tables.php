<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_accounts', function (Blueprint $table) {
            $table->text('refresh_token')->nullable();
            $table->string('client_id')->nullable();
        });
        Schema::create('financial_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('submission_key');
            $table->char('fingerprint', 64);
            $table->unsignedSmallInteger('response_status');
            $table->longText('response_body');
            $table->timestamp('created_at');
            $table->unique(['user_id', 'submission_key']);
        });
        Schema::create('account_deletion_tokens', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('created_at');
        });
        Schema::create('apple_token_revocations', function (Blueprint $table) {
            $table->id();
            $table->text('token');
            $table->string('client_id');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('apple_token_revocations');
        Schema::dropIfExists('account_deletion_tokens');
        Schema::dropIfExists('financial_submissions');
        Schema::table('social_accounts', function (Blueprint $table) {
            $table->dropColumn(['refresh_token', 'client_id']);
        });
    }
};
