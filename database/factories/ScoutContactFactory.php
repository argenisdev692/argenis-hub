<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\LeadScout\Domain\Enums\ContactSource;
use Modules\LeadScout\Domain\Enums\EmailKind;
use Modules\LeadScout\Domain\Enums\RoleCategory;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutContactEloquentModel;

/**
 * @extends Factory<ScoutContactEloquentModel>
 */
final class ScoutContactFactory extends Factory
{
    protected $model = ScoutContactEloquentModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid7(),
            'company_id' => ScoutCompanyFactory::new(),
            'full_name' => fake()->name(),
            'role_title' => 'CEO',
            'role_category' => RoleCategory::Founder,
            'is_primary' => false,
            'source' => ContactSource::Website,
            'evidence_url' => 'https://example.com/equipo',
            'evidence_excerpt' => 'CEO y fundador.',
            'evidence_captured_at' => now(),
            'last_verified_at' => now(),
        ];
    }

    public function primary(): self
    {
        return $this->state(fn (): array => ['is_primary' => true]);
    }

    public function withPublishedEmail(string $email): self
    {
        return $this->state(fn (): array => [
            'published_email' => $email,
            'email_kind' => EmailKind::Nominative,
        ]);
    }
}
