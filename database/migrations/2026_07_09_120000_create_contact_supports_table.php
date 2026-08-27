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
        Schema::create('contact_supports', function (Blueprint $table) {
            $table->id();

            $table->string('uuid')->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->string('phone', 20);
            $table->string('subject', 150);
            $table->text('message');
            $table->boolean('sms_consent')->default(false);
            $table->boolean('readed')->default(false);

            // Anti-spam verdict written by the public submission pipeline
            // (SpamGuard + spatie/laravel-honeypot): `is_spam` drives the admin
            // "Spam" filter/folder; `spam_score` + `spam_reasons` explain the
            // verdict so an operator can restore a false positive with context.
            $table->boolean('is_spam')->default(false);
            $table->unsignedSmallInteger('spam_score')->default(0);
            $table->json('spam_reasons')->nullable();

            $table->softDeletes();
            $table->timestamps();

            // Inbox read/unread filter (Prisma idx_contact_supports_readed).
            $table->index('readed', 'idx_contact_supports_readed');
            // Admin inbox spam/ham filter.
            $table->index('is_spam', 'idx_contact_supports_is_spam');
            // Canonical list filter pattern (status via deleted_at + default
            // ordering by created_at) — BACKEND-PHP §4.1.
            $table->index(['deleted_at', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_supports');
    }
};
