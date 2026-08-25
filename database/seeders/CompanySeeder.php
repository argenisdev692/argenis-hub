<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\CompanyData;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds the main company record including fiscal identity and bank details
 * used on invoice PDFs.
 */
class CompanySeeder extends Seeder
{
    public function run(): void
    {
        $userId = User::query()
            ->where('email', 'argenis692@gmail.com')
            ->value('id');

        if (! is_int($userId)) {
            throw new \RuntimeException('UserSeeder must create argenis692@gmail.com before CompanySeeder runs.');
        }

        // Lookup by owner so changing the public email does not spawn a second row.
        $company = CompanyData::query()->firstOrNew(['user_id' => $userId]);
        if (! $company->exists) {
            $company->uuid = (string) Str::uuid7();
        }

        $company->fill([
            'name' => 'Argenis Carrillo Gonzalez',
            'company_name' => 'Argenis Hub',
            'description' => 'Argenis Hub is an AI-powered CRM for a single operator, covering five modules on '
                .'one record set: AI-assisted content generation for social media posts, lead campaign '
                .'creation and scheduling, appointment booking and management, invoicing with branded PDF '
                .'exports, and an ATS module for CV optimisation and job-match search.',
            'signature_path' => null,
            'email' => 'info@argenis.dev',
            'phone' => '+351 963 490 414',
            'nif_nipc' => '316416584',
            'nie' => '2175V64V7',
            'bank_beneficiary' => 'Argenis Jose Carrillo Gonzalez',
            'bank_iban' => 'PT50 0036 0011 9910 0063 053 49',
            'bank_bic' => 'MPIOPTPL',
            'bank_name' => 'Montepio',
            'invoice_notes' => "VAT - Reverse Charge: International transaction exempt from VAT. Cross-border service provision between Portugal and United States (B2B).\nWeb development services provided remotely.",
            'address' => 'Rua da Saudade, Nº 1, R/C Esq.',
            'address_2' => null,
            'zip_code' => '6200-386',
            'city' => 'Covilhã',
            'state' => 'Castelo Branco',
            'country' => 'Portugal',
            'country_code' => 'PT',
            'website' => null,
            'user_id' => $userId,
            'facebook_link' => 'https://www.facebook.com/argenisdev692/',
            'github_link' => 'https://github.com/argenisdev692',
            'instagram_link' => 'https://www.instagram.com/argenis.dev/',
            'linkedin_link' => 'https://www.linkedin.com/in/argenisdev692/',
            'twitter_link' => null,
            'tiktok_link' => 'https://www.tiktok.com/@argenisdev692?lang=es-419',
            'latitude' => 40.2806,
            'longitude' => -7.5049,
        ]);
        $company->save();
    }
}
