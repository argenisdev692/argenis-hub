<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioPostingEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioProfileEloquentModel;

/**
 * @extends Factory<StudioPostingEloquentModel>
 */
final class StudioPostingFactory extends Factory
{
    protected $model = StudioPostingEloquentModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $url = 'https://boards.greenhouse.io/acme/jobs/'.fake()->unique()->randomNumber(6);

        return [
            'uuid' => (string) Str::uuid7(),
            'user_id' => User::factory(),
            'profile_id' => StudioProfileEloquentModel::factory(),
            'canonical_url' => $url,
            'url_hash' => hash('sha256', $url),
            'source' => 'greenhouse',
            'employer_name' => fake()->company(),
            'title' => 'Senior Fullstack Developer (Laravel + Vue)',
            'location_text' => 'Remote, EU',
            'remote_scope' => 'remote_eu',
            'status' => 'new',
            'discovery_channel' => 'employer_ats',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ];
    }
}
