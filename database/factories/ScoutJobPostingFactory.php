<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\LeadScout\Domain\Enums\ContractType;
use Modules\LeadScout\Domain\Enums\PostingStatus;
use Modules\LeadScout\Domain\Enums\RemoteMode;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutJobPostingEloquentModel;

/**
 * @extends Factory<ScoutJobPostingEloquentModel>
 */
final class ScoutJobPostingFactory extends Factory
{
    protected $model = ScoutJobPostingEloquentModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid7(),
            'company_id' => ScoutCompanyFactory::new(),
            'fingerprint' => hash('sha256', (string) Str::uuid7()),
            'title' => 'Desarrollador Laravel + Vue (freelance, remoto)',
            'country' => 'ES',
            'remote_mode' => RemoteMode::Remote,
            'contract_type' => ContractType::Freelance,
            'language' => 'es',
            'published_at' => now()->subDays(3),
            'status' => PostingStatus::Active,
            'source_url' => 'https://example.com/jobs/'.Str::uuid7()->toString(),
            'body_text' => 'Buscamos desarrollador Laravel con Vue para colaboración freelance.',
        ];
    }

    public function unresolved(): self
    {
        return $this->state(fn (): array => ['company_id' => null]);
    }

    public function expired(): self
    {
        return $this->state(fn (): array => [
            'status' => PostingStatus::Expired,
            'published_at' => now()->subDays(60),
        ]);
    }
}
