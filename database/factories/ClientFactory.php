<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Clients\Domain\Enums\ClientStatus;
use Modules\Clients\Infrastructure\Persistence\Eloquent\Models\ClientEloquentModel;

/**
 * @extends Factory<ClientEloquentModel>
 */
final class ClientFactory extends Factory
{
    protected $model = ClientEloquentModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid7(),
            'user_id' => User::factory(),
            'client_name' => mb_strtoupper((string) fake()->unique()->company()),
            'email' => fake()->unique()->companyEmail(),
            'status' => ClientStatus::Active,
            'phone' => '+1'.fake()->numerify('##########'),
            'address' => fake()->address(),
            'country' => fake()->country(),
            'country_code' => mb_strtoupper(fake()->countryCode()),
            'tax_id' => (string) fake()->numberBetween(0, 9),
            'nif' => mb_strtoupper(fake()->bothify('?#######?')),
            'website' => 'https://'.fake()->domainName(),
            'facebook_link' => null,
            'instagram_link' => null,
            'linkedin_link' => null,
            'twitter_link' => null,
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function draft(): self
    {
        return $this->state(fn (): array => ['status' => ClientStatus::Draft]);
    }

    /**
     * Matches the factory default, but stated explicitly: the invoice suites
     * only ever bill an ACTIVE client, and saying so keeps them readable if the
     * default lifecycle ever changes.
     */
    public function active(): self
    {
        return $this->state(fn (): array => ['status' => ClientStatus::Active]);
    }

    public function inactive(): self
    {
        return $this->state(fn (): array => ['status' => ClientStatus::Inactive]);
    }
}
