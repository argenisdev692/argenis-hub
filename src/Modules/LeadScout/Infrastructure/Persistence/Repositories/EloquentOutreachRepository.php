<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Persistence\Repositories;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Modules\LeadScout\Domain\Entities\Outreach;
use Modules\LeadScout\Domain\Enums\LegalRuleStatus;
use Modules\LeadScout\Domain\Enums\MessageVariant;
use Modules\LeadScout\Domain\Enums\OutreachChannel;
use Modules\LeadScout\Domain\Enums\OutreachKind;
use Modules\LeadScout\Domain\Enums\OutreachStage;
use Modules\LeadScout\Domain\Enums\ReplyOutcome;
use Modules\LeadScout\Domain\Ports\OutreachRepositoryPort;
use Modules\LeadScout\Domain\ValueObjects\NewOutreachDraft;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutOutreachEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Mappers\OutreachMapper;

/**
 * Stage moves and their history event are written in one transaction.
 */
final readonly class EloquentOutreachRepository implements OutreachRepositoryPort
{
    public function byUuid(string $uuid): ?Outreach
    {
        $model = ScoutOutreachEloquentModel::query()->with(OutreachMapper::RELATIONS)->where('uuid', $uuid)->first();

        return $model === null ? null : OutreachMapper::toEntity($model);
    }

    public function forCompany(int $companyId): array
    {
        return ScoutOutreachEloquentModel::query()
            ->with(OutreachMapper::RELATIONS)
            ->where('company_id', $companyId)
            ->orderByDesc('created_at')
            ->get()
            ->map(OutreachMapper::toEntity(...))
            ->values()
            ->all();
    }

    public function createDraft(NewOutreachDraft $draft): Outreach
    {
        $model = ScoutOutreachEloquentModel::query()->create([
            'company_id' => $draft->companyId,
            'contact_id' => $draft->contactId,
            'contact_channel_id' => $draft->contactChannelId,
            'operator_id' => $draft->operatorId,
            'outreach_kind' => $draft->outreachKind->value,
            'send_medium' => null,
            'sender_kind' => $draft->senderKind,
            'stage' => OutreachStage::Draft->value,
            'draft_body' => $draft->draftBody,
            'variant' => $draft->variant->value,
            'signal_used' => $draft->signalUsed,
            'template_key' => $draft->templateKey,
            'template_version' => $draft->templateVersion,
            'ai_provider' => $draft->aiProvider,
            'ai_model' => $draft->aiModel,
            'channel_warning' => $draft->channelWarning,
            'legal_rule_status' => $draft->legalRuleStatus?->value,
        ]);

        return OutreachMapper::toEntity($model->load(OutreachMapper::RELATIONS));
    }

    public function recordImportedContact(
        int $companyId,
        int $operatorId,
        OutreachChannel $medium,
        ?MessageVariant $variant,
        string $senderKind,
        DateTimeImmutable $sentAt,
        OutreachStage $reachedStage,
        string $notes,
    ): Outreach {
        return DB::transaction(static function () use ($companyId, $operatorId, $medium, $variant, $senderKind, $sentAt, $reachedStage, $notes): Outreach {
            $model = ScoutOutreachEloquentModel::query()->create([
                'company_id' => $companyId,
                'operator_id' => $operatorId,
                'outreach_kind' => OutreachKind::ContractorOffer->value,
                'send_medium' => $medium->value,
                'sender_kind' => $senderKind,
                'stage' => OutreachStage::Sent->value,
                'variant' => $variant?->value,
                'sent_at' => $sentAt,
                'stage_changed_at' => $sentAt,
                'notes' => $notes,
            ]);

            $model->stageEvents()->create([
                'from_stage' => null,
                'to_stage' => OutreachStage::Sent->value,
                'operator_id' => $operatorId,
                'created_at' => $sentAt,
            ]);

            if ($reachedStage !== OutreachStage::Sent) {
                $model->update(['stage' => $reachedStage->value]);

                $model->stageEvents()->create([
                    'from_stage' => OutreachStage::Sent->value,
                    'to_stage' => $reachedStage->value,
                    'operator_id' => $operatorId,
                ]);
            }

            return OutreachMapper::toEntity($model->load(OutreachMapper::RELATIONS));
        });
    }

    public function annotate(Outreach $outreach, ?string $draftBody, ?string $notes): Outreach
    {
        $changes = array_filter(
            ['draft_body' => $draftBody, 'notes' => $notes],
            static fn (?string $value): bool => $value !== null,
        );

        return $this->write($outreach, $changes);
    }

    public function recordSend(
        Outreach $outreach,
        int $contactChannelId,
        OutreachChannel $medium,
        string $senderKind,
        ?LegalRuleStatus $legalRuleStatus,
        ?DateTimeImmutable $legalAckAt,
        DateTimeImmutable $sentAt,
    ): Outreach {
        return $this->write($outreach, [
            'contact_channel_id' => $contactChannelId,
            'send_medium' => $medium->value,
            'sender_kind' => $senderKind,
            'legal_rule_status' => $legalRuleStatus?->value,
            'legal_ack_at' => $legalAckAt,
            'sent_at' => $sentAt,
            'stage_changed_at' => $sentAt,
        ]);
    }

    public function moveToStage(
        Outreach $outreach,
        OutreachStage $to,
        int $operatorId,
        ?ReplyOutcome $replyOutcome = null,
        ?DateTimeImmutable $repliedAt = null,
    ): Outreach {
        return DB::transaction(static function () use ($outreach, $to, $operatorId, $replyOutcome, $repliedAt): Outreach {
            $model = ScoutOutreachEloquentModel::query()->findOrFail($outreach->id);
            $from = $model->stage;

            $model->update([
                'stage' => $to->value,
                'stage_changed_at' => now(),
                'reply_outcome' => $replyOutcome?->value ?? $model->reply_outcome?->value,
                'replied_at' => $replyOutcome === null ? $model->replied_at : ($repliedAt ?? now()),
            ]);

            $model->stageEvents()->create([
                'from_stage' => $from->value,
                'to_stage' => $to->value,
                'reply_outcome' => $replyOutcome?->value,
                'operator_id' => $operatorId,
            ]);

            return OutreachMapper::toEntity($model->refresh()->load(OutreachMapper::RELATIONS));
        });
    }

    public function countSentOn(int $operatorId, DateTimeImmutable $day): int
    {
        return ScoutOutreachEloquentModel::query()
            ->where('operator_id', $operatorId)
            ->where('stage', OutreachStage::Sent->value)
            ->whereDate('sent_at', $day->format('Y-m-d'))
            ->count();
    }

    public function countSent(): int
    {
        return ScoutOutreachEloquentModel::query()->whereNotNull('sent_at')->count();
    }

    public function countInStages(array $stages): int
    {
        return ScoutOutreachEloquentModel::query()
            ->whereIn('stage', array_map(static fn (OutreachStage $stage): string => $stage->value, $stages))
            ->count();
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function write(Outreach $outreach, array $changes): Outreach
    {
        $model = ScoutOutreachEloquentModel::query()->findOrFail($outreach->id);

        if ($changes !== []) {
            $model->update($changes);
        }

        return OutreachMapper::toEntity($model->load(OutreachMapper::RELATIONS));
    }
}
