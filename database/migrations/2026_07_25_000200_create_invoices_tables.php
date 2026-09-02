<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnUpdate()->restrictOnDelete();
            // Optional "primary product" for a single-course / single-video
            // invoice. Line items stay authoritative for what is billed.
            $table->foreignId('product_id')->nullable()->constrained('products')->cascadeOnUpdate()->nullOnDelete();

            $table->string('invoice_number', 32);
            $table->unsignedInteger('sequence');
            $table->unsignedSmallInteger('year');

            $table->date('issue_date');
            $table->date('due_date');

            $table->char('currency', 3)->default('USD');
            $table->string('tax_mode', 16)->default('EXEMPT');
            $table->decimal('tax_rate', 8, 4)->nullable()->default(0);
            $table->string('tax_label', 32)->default('IVA');

            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);

            $table->boolean('is_paid')->default(false);
            // PaymentMethod enum value (REMITLY, BANK_TRANSFER, WISE, …).
            $table->string('payment_method', 32)->nullable();
            $table->foreignId('payment_account_id')
                ->nullable()
                ->constrained('payment_accounts')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            // Immutable snapshot of the account as it stood when the invoice was
            // issued — an invoice is a legal document, editing an account later
            // must never rewrite an already-delivered PDF.
            $table->json('payment_details_json')->nullable();
            $table->string('transfer_number')->nullable();
            $table->date('payment_date')->nullable();
            $table->decimal('amount_received', 12, 2)->nullable();

            $table->text('notes')->nullable();
            $table->text('additional_notes')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->unique(['year', 'sequence']);
            $table->unique('invoice_number');
            $table->index('deleted_at', 'idx_invoices_deleted_at');
            $table->index(['deleted_at', 'created_at']);
            $table->index(['deleted_at', 'issue_date']);
            $table->index(['client_id', 'created_at']);
            $table->index(['year', 'sequence']);
            $table->index('is_paid');
            $table->index(['product_id', 'created_at']);
        });

        Schema::create('invoice_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            // InvoiceItemKind: SERVICE | COURSE | VIDEO | CUSTOM.
            $table->string('kind', 16)->default('CUSTOM');
            // InvoiceItemUnit: UNIT | HOUR | SESSION | MONTH — drives both the
            // rendered quantity ("25 horas") and the unit-price column header
            // ("Precio/Hora" vs "Precio unitario").
            $table->string('unit', 16)->default('UNIT');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('title');
            // TEXT, not string(500): training lines carry a bulleted session
            // breakdown (dates, hours, schedule, modality) that overruns 500.
            $table->text('description')->nullable();
            $table->decimal('quantity', 12, 2)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('amount', 12, 2)->default(0);
            $table->timestamps();

            $table->index(['invoice_id', 'sort_order']);
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
    }
};
