<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();

            $table->string('client_name');
            $table->string('email')->nullable();
            $table->string('status', 50)->default('DRAFT');
            $table->string('phone', 20);
            $table->string('address')->nullable();
            $table->string('country')->nullable();
            $table->char('country_code', 2)->nullable();
            $table->string('tax_id')->nullable();
            $table->string('nif')->nullable();

            $table->string('website')->nullable();
            $table->string('facebook_link')->nullable();
            $table->string('instagram_link')->nullable();
            $table->string('linkedin_link')->nullable();
            $table->string('twitter_link')->nullable();

            $table->text('notes')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index('deleted_at', 'idx_clients_deleted_at');
            $table->index(['deleted_at', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
