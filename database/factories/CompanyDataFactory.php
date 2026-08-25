<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CompanyData;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CompanyData>
 */
class CompanyDataFactory extends Factory
{
    protected $model = CompanyData::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid7(),
            'name' => fake()->name(),
            'company_name' => fake()->company(),
            'description' => fake()->sentence(),
            'email' => fake()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->streetAddress(),
            'zip_code' => fake()->postcode(),
            'city' => fake()->city(),
            'state' => fake()->word(),
            'country' => 'Portugal',
            'country_code' => 'PT',
            'user_id' => User::factory(),
        ];
    }

    /**
     * A row with every social channel populated — the shape the landing footer
     * and the email footer render at full width.
     */
    public function withSocials(): self
    {
        return $this->state(fn (): array => [
            'linkedin_link' => 'https://www.linkedin.com/in/'.fake()->userName(),
            'github_link' => 'https://github.com/'.fake()->userName(),
            'instagram_link' => 'https://www.instagram.com/'.fake()->userName(),
            'facebook_link' => 'https://www.facebook.com/'.fake()->userName(),
            'tiktok_link' => 'https://www.tiktok.com/@'.fake()->userName(),
            'twitter_link' => 'https://x.com/'.fake()->userName(),
        ]);
    }
}
