<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\LeadScout\Infrastructure\Persistence\Supabase\SupabaseRls;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scout_contact_objections', function (Blueprint $table): void {
            $table->id();
            $table->string('person_hash', 64)->unique();
            $table->timestamps();
        });

        SupabaseRls::protect('scout_contact_objections');
    }

    public function down(): void
    {
        Schema::dropIfExists('scout_contact_objections');
    }
};
