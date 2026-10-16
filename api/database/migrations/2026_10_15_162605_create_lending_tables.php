<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lending_contacts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('owner_id')->constrained('users');
            $table->string('name', 120);
            $table->text('email')->nullable();
            $table->string('email_hash', 64)->nullable()->index();
            $table->foreignId('connected_user_id')->nullable()->constrained('users');
            $table->string('invitation_status')->nullable();
            $table->timestamp('invitation_expires_at')->nullable();
            $table->timestamp('disconnected_at')->nullable();
            $table->timestamps();
            $table->index(['owner_id', 'updated_at']);
        });
        Schema::create('lending_loans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lending_contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->string('direction', 12);
            $table->unsignedBigInteger('amount_minor');
            $table->unsignedBigInteger('repaid_minor')->default(0);
            $table->string('currency_code', 3);
            $table->foreign('currency_code')->references('code')->on('currencies');
            $table->date('occurred_on');
            $table->date('due_on')->nullable();
            $table->string('description', 500)->nullable();
            $table->text('private_note')->nullable();
            $table->timestamp('shared_at')->nullable();
            $table->foreignId('shared_with')->nullable()->constrained('users');
            $table->string('acknowledgement', 20)->default('private');
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('void_requested_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->index(['lending_contact_id', 'currency_code', 'direction']);
            $table->index(['shared_with', 'lending_contact_id']);
        });
        Schema::create('lending_repayments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lending_contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recorded_by')->constrained('users');
            $table->foreignId('confirmed_by')->nullable()->constrained('users');
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency_code', 3);
            $table->string('direction', 12);
            $table->date('occurred_on');
            $table->string('note', 500)->nullable();
            $table->string('status', 20);
            $table->timestamps();
            $table->index(['lending_contact_id', 'status']);
        });
        Schema::create('lending_repayment_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lending_repayment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lending_loan_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->unique(['lending_repayment_id', 'lending_loan_id'], 'lending_allocation_unique');
        });
        Schema::create('lending_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lending_loan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->constrained('users');
            $table->string('event', 60);
            $table->json('details')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lending_events');
        Schema::dropIfExists('lending_repayment_allocations');
        Schema::dropIfExists('lending_repayments');
        Schema::dropIfExists('lending_loans');
        Schema::dropIfExists('lending_contacts');
    }
};
