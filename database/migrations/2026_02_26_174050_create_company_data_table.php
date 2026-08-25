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
        Schema::create('company_data', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->string('name')->nullable();
            $table->string('company_name');
            $table->text('description')->nullable();
            $table->string('signature_path')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('nif_nipc')->nullable();
            $table->string('nie')->nullable();
            $table->string('bank_beneficiary')->nullable();
            $table->string('bank_iban')->nullable();
            $table->string('bank_bic')->nullable();
            $table->string('bank_name')->nullable();
            $table->text('invoice_notes')->nullable();
            $table->text('address')->nullable();
            $table->text('address_2')->nullable();
            $table->string('zip_code')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable();
            $table->string('country_code', 2)->nullable();
            $table->double('latitude')->nullable();
            $table->double('longitude')->nullable();
            $table->string('website')->nullable();
            $table->string('facebook_link')->nullable();
            $table->string('instagram_link')->nullable();
            $table->string('linkedin_link')->nullable();
            $table->string('twitter_link')->nullable();
            $table->string('tiktok_link')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('logo_white_path')->nullable();
            $table->string('mark_path')->nullable();
            $table->foreignId('user_id')->constrained();

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_data');
    }
};
