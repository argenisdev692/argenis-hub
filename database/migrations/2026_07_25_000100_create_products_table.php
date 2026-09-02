<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Billable catalog: live training courses and video courses, so an invoice can
 * bill a course or a video the same way it bills a freelance service.
 *
 * Deliberately the catalog HEADER only — the content tree (sessions, topics,
 * scripts, materials, AI generation ledger) drafted in
 * `specs/INVOICE-MODULE/PRODUCTS-MODULE/` is additive and lands later without
 * touching this table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('clients')->cascadeOnUpdate()->nullOnDelete();

            $table->string('type', 32);
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();

            $table->decimal('price', 10, 2)->default(0);
            $table->char('currency', 3)->default('EUR');
            // Drives the invoice line: `25 horas x 52,00 EUR/hora` vs `1 x 312,00 EUR`.
            $table->string('default_unit', 16)->default('HOUR');

            $table->string('status', 16)->default('DRAFT');
            $table->string('thumbnail')->nullable();
            $table->string('level', 32)->default('beginner');
            $table->string('language', 8)->default('es');

            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('total_hours', 8, 2)->nullable();
            $table->unsignedInteger('total_sessions')->nullable();
            $table->string('modality', 16)->nullable();
            $table->text('notes')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index('user_id');
            $table->index('client_id');
            $table->index('type');
            $table->index(['deleted_at', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
