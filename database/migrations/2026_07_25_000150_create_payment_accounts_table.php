<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issuer payment accounts — how the client is asked to pay, and how a paid
 * invoice records that it was paid.
 *
 * Method and currency are ORTHOGONAL on purpose: Remitly/USD, bank transfer/EUR
 * and bank transfer/USD all coexist. `currency = null` means "usable for any
 * currency". The invoice snapshots the resolved account into
 * `invoices.payment_details_json`, so editing an account never rewrites the
 * history of an already-issued document.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();

            $table->string('method', 32);
            $table->char('currency', 3)->nullable();
            $table->string('label');

            $table->string('beneficiary')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('iban', 64)->nullable();
            $table->string('bic', 32)->nullable();
            $table->string('account_number', 64)->nullable();
            $table->string('routing_number', 64)->nullable();
            $table->string('holder_email')->nullable();
            $table->string('holder_phone', 32)->nullable();
            $table->text('instructions')->nullable();

            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->softDeletes();
            $table->timestamps();

            $table->index('user_id');
            $table->index(['deleted_at', 'created_at']);
            // Backs "the default account for this currency" on the invoice form.
            $table->index(['currency', 'is_default']);
            $table->index(['method', 'currency']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_accounts');
    }
};
