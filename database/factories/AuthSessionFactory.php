<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Auth\Domain\ValueObjects\DeviceFingerprint;
use Modules\Auth\Infrastructure\Persistence\Eloquent\Models\AuthSessionEloquentModel;

/**
 * @extends Factory<AuthSessionEloquentModel>
 */
final class AuthSessionFactory extends Factory
{
    protected $model = AuthSessionEloquentModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $ipAddress = fake()->ipv4();
        $userAgent = fake()->userAgent();

        return [
            'uuid' => (string) Str::uuid7(),
            'user_id' => User::factory(),
            'session_id' => Str::random(40),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'device_hash' => DeviceFingerprint::fromRequestSignals($userAgent, $ipAddress)->hash,
            'last_seen_at' => now(),
            'revoked_at' => null,
        ];
    }

    public function revoked(): static
    {
        return $this->state(fn (array $attributes): array => [
            'revoked_at' => now(),
        ]);
    }
}
