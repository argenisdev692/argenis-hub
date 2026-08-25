<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Roles/permissions are created by RolePermissionSeeder, which MUST run first.
     * Passwords are passed in plain text and hashed by the model's `hashed` cast
     * (Argon2id per config/hashing.php).
     */
    public function run(): void
    {
        $superAdmin = User::firstOrCreate(
            ['email' => 'argenis692@gmail.com'],
            [
                'uuid' => (string) Str::uuid7(),
                'first_name' => 'Argenis',
                'last_name' => 'Gonzalez',
                'username' => 'argenis692',
                'password' => 'argenis01=',
                'address' => '123 Random Web Dev St, Suite 404, Tech City',
                'country' => 'United States',
                'country_code' => 'US',
                'terms_and_conditions' => true,
                'email_verified_at' => now(),
            ],
        );
        $superAdmin->syncRoles('SUPER_ADMIN');

        $admin = User::firstOrCreate(
            ['email' => 'admin@vidula.com'],
            [
                'uuid' => (string) Str::uuid7(),
                'first_name' => 'Admin',
                'last_name' => 'User',
                'username' => 'admin_user',
                'password' => 'password123',
                'address' => 'Admin Address, City',
                'country' => 'United States',
                'country_code' => 'US',
                'terms_and_conditions' => true,
                'email_verified_at' => now(),
            ],
        );
        $admin->syncRoles('ADMIN');

        $standard = User::firstOrCreate(
            ['email' => 'user@vidula.com'],
            [
                'uuid' => (string) Str::uuid7(),
                'first_name' => 'Standard',
                'last_name' => 'User',
                'username' => 'standard_user',
                'password' => 'password123',
                'address' => 'User Address, City',
                'country' => 'United States',
                'country_code' => 'US',
                'terms_and_conditions' => true,
                'email_verified_at' => now(),
            ],
        );
        $standard->syncRoles('USER');

        $guest = User::firstOrCreate(
            ['email' => 'guest@vidula.com'],
            [
                'uuid' => (string) Str::uuid7(),
                'first_name' => 'Guest',
                'last_name' => 'User',
                'username' => 'guest_user',
                'password' => 'password123',
                'address' => 'Guest Address, City',
                'country' => 'United States',
                'country_code' => 'US',
                'terms_and_conditions' => true,
                'email_verified_at' => now(),
            ],
        );
        $guest->syncRoles('GUEST');
    }
}
