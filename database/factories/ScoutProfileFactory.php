<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutProfileEloquentModel;

/**
 * @extends Factory<ScoutProfileEloquentModel>
 */
final class ScoutProfileFactory extends Factory
{
    protected $model = ScoutProfileEloquentModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid7(),
            'user_id' => User::factory(),
            'version' => 1,
            'confirmed_skills' => ['laravel', 'php', 'vue', 'inertia', 'livewire', 'postgresql'],
            'potential_skills' => ['aws', 'pest', 'forge'],
            'proof_points' => [],
            'languages' => ['es' => 'native', 'pt' => 'resident', 'en' => 'B1'],
            'is_current' => true,
        ];
    }
}
