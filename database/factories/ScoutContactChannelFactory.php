<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\LeadScout\Domain\Enums\ChannelAudience;
use Modules\LeadScout\Domain\Enums\ChannelStatus;
use Modules\LeadScout\Domain\Enums\ChannelType;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutContactChannelEloquentModel;

/**
 * @extends Factory<ScoutContactChannelEloquentModel>
 */
final class ScoutContactChannelFactory extends Factory
{
    protected $model = ScoutContactChannelEloquentModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid7(),
            'company_id' => ScoutCompanyFactory::new(),
            'channel_type' => ChannelType::ContactForm,
            'url' => 'https://example.com/contacto',
            'audience' => ChannelAudience::LeadershipSales,
            'evidence_url' => 'https://example.com/contacto',
            'evidence_excerpt' => 'Escríbenos a través del formulario.',
            'status' => ChannelStatus::Active,
        ];
    }
}
