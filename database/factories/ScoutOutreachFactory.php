<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\LeadScout\Domain\Enums\MessageVariant;
use Modules\LeadScout\Domain\Enums\OutreachChannel;
use Modules\LeadScout\Domain\Enums\OutreachKind;
use Modules\LeadScout\Domain\Enums\OutreachStage;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutOutreachEloquentModel;

/**
 * @extends Factory<ScoutOutreachEloquentModel>
 */
final class ScoutOutreachFactory extends Factory
{
    protected $model = ScoutOutreachEloquentModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid7(),
            'company_id' => ScoutCompanyFactory::new(),
            'operator_id' => User::factory(),
            'outreach_kind' => OutreachKind::ContractorOffer,
            'stage' => OutreachStage::Draft,
            'variant' => MessageVariant::Vacancy,
            'template_key' => 'vacancy_reply',
            'template_version' => 1,
        ];
    }

    public function sent(): self
    {
        return $this->state(fn (): array => [
            'stage' => OutreachStage::Sent,
            'send_medium' => OutreachChannel::ContactForm,
            'sender_kind' => 'business_domain',
            'sent_at' => now(),
            'stage_changed_at' => now(),
        ]);
    }
}
