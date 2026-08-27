<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Modules\Clients\Infrastructure\Persistence\Eloquent\Models\ClientEloquentModel;

/**
 * Recurring CRM clients used for invoices, products, and demos.
 */
final class ClientSeeder extends Seeder
{
    public function run(): void
    {
        $userId = User::query()
            ->where('email', 'argenis692@gmail.com')
            ->value('id');

        if (! is_int($userId)) {
            throw new \RuntimeException('UserSeeder must create argenis692@gmail.com before ClientSeeder runs.');
        }

        $clients = [
            [
                'client_name' => 'IMAGINA WEB & MOBILE TECHNOLOGIES S.L.',
                'email' => 'iformacion@imaginagroup.com',
                'phone' => '+34673566782',
                'status' => 'ACTIVE',
                'tax_id' => '0',
                'nif' => 'B98330335',
                'address' => 'Avda. Manuel de Falla, 12 Duplicado, 46015 Valencia, España',
                'country' => 'Spain',
                'country_code' => 'ES',
                'website' => 'https://imaginaformacion.com/',
                'facebook_link' => 'https://www.facebook.com/ImaginaGroup/',
                'linkedin_link' => 'https://www.linkedin.com/company/imagina-group/',
                'twitter_link' => 'https://x.com/iformacion',
            ],
            [
                'client_name' => 'AQUASHIELD RESTORATION LLC',
                'email' => 'info@aquashieldrestorationusa.com',
                'phone' => '+17135876423',
                'status' => 'ACTIVE',
                'tax_id' => '0',
                'nif' => '36-5164436',
                'address' => '1321 Upland Dr. PMB 4455, 77043 Houston, Texas, United States',
                'country' => 'United States',
                'country_code' => 'US',
            ],
            [
                'client_name' => 'CESAR AUGUSTO GONZALEZ',
                'email' => 'cesarmilenario@gmail.com',
                'phone' => '+34603105307',
                'status' => 'ACTIVE',
                'tax_id' => '0',
                'nif' => 'Y7150874G',
                'address' => 'Calle delicias 42 Portal A, Piso 1E, 35110 Vecindario, Las Palmas, España',
                'country' => 'spain',
                'country_code' => 'ES',
            ],
        ];

        foreach ($clients as $attributes) {
            $client = ClientEloquentModel::query()->firstOrNew([
                'client_name' => $attributes['client_name'],
            ]);

            if (! $client->exists) {
                $client->uuid = (string) Str::uuid7();
            }

            $client->fill([
                'client_name' => $attributes['client_name'],
                'email' => $attributes['email'] ?? null,
                'phone' => $attributes['phone'],
                'status' => $attributes['status'],
                'tax_id' => $attributes['tax_id'],
                'nif' => $attributes['nif'],
                'address' => $attributes['address'],
                'country' => $attributes['country'] ?? null,
                'country_code' => $attributes['country_code'] ?? null,
                'website' => $attributes['website'] ?? null,
                'facebook_link' => $attributes['facebook_link'] ?? null,
                'instagram_link' => $attributes['instagram_link'] ?? null,
                'linkedin_link' => $attributes['linkedin_link'] ?? null,
                'twitter_link' => $attributes['twitter_link'] ?? null,
                'notes' => null,
                'user_id' => $userId,
            ]);

            $client->save();
        }
    }
}
