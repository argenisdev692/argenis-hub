<?php

declare(strict_types=1);

namespace Modules\Campaigns\Infrastructure\Persistence\Eloquent\Models;

use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\CampaignFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Modules\Campaigns\Application\DTOs\CampaignFilterData;
use Modules\Campaigns\Domain\Enums\CampaignAdFormat;
use Modules\Campaigns\Domain\Enums\CampaignBrandVoice;
use Modules\Campaigns\Domain\Enums\CampaignBusinessGoal;
use Modules\Campaigns\Domain\Enums\CampaignFunnelStage;
use Modules\Campaigns\Domain\Enums\CampaignLanguage;
use Modules\Campaigns\Domain\Enums\CampaignPlatform;
use Modules\Campaigns\Domain\Enums\CampaignStatus;
use Shared\Domain\Ports\StoragePort;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Throwable;

/**
 * @property int $id
 * @property string $uuid
 * @property string|null $niche
 * @property string $topic
 * @property string|null $angle
 * @property string|null $hook
 * @property string|null $key_trend
 * @property string|null $audience
 * @property CampaignBusinessGoal $business_goal
 * @property CampaignBrandVoice $brand_voice
 * @property CampaignFunnelStage $funnel_stage
 * @property CampaignPlatform $platform
 * @property CampaignAdFormat $ad_format
 * @property CampaignLanguage $language
 * @property string $provider
 * @property CampaignStatus $status
 * @property Carbon|null $scheduled_at
 * @property Carbon|null $published_at
 * @property string|null $headline
 * @property string|null $primary_text
 * @property string|null $description
 * @property string|null $call_to_action
 * @property list<string>|null $hashtags
 * @property list<string>|null $lead_form_questions
 * @property list<string>|null $targeting_suggestions
 * @property array<string, mixed>|null $platforms
 * @property string|null $cover_image_path
 * @property string|null $cover_image_prompt
 * @property array<string, mixed>|null $scores
 * @property int|null $audience_fit_score
 * @property int|null $virality_score
 * @property int|null $roi_potential_score
 * @property int|null $lead_quality_score
 * @property int|null $trend_relevance_score
 * @property int|null $overall_score_avg
 * @property string|null $success_probability_label
 * @property bool $all_scores_pass
 * @property int|null $iterations_required
 * @property bool $quality_warning
 * @property string|null $quality_warning_message
 * @property list<string>|null $optimization_suggestions
 * @property list<array<string, mixed>>|null $research_sources
 * @property list<string>|null $tavily_data_used
 * @property array<string, mixed>|null $ai_detection_risk
 * @property int|null $created_by
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string|null $cover_image_url
 * @property-read User|null $creator
 *
 * @mixin \Eloquent
 */
#[Table('campaigns')]
#[Fillable([
    'uuid', 'niche', 'topic', 'angle', 'hook', 'key_trend', 'audience', 'business_goal',
    'brand_voice', 'funnel_stage', 'platform', 'ad_format', 'language', 'provider', 'status',
    'scheduled_at', 'published_at', 'headline', 'primary_text', 'description', 'call_to_action',
    'hashtags', 'lead_form_questions', 'targeting_suggestions', 'platforms', 'cover_image_path',
    'cover_image_prompt', 'scores', 'audience_fit_score', 'virality_score', 'roi_potential_score',
    'lead_quality_score', 'trend_relevance_score', 'overall_score_avg', 'success_probability_label',
    'all_scores_pass', 'iterations_required', 'quality_warning', 'quality_warning_message',
    'optimization_suggestions', 'research_sources', 'tavily_data_used', 'ai_detection_risk', 'created_by',
])]
final class CampaignEloquentModel extends Model
{
    /** @use HasFactory<CampaignFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $hidden = ['id', 'created_by'];

    /**
     * @var list<string>
     */
    protected $appends = ['cover_image_url'];

    protected static function booted(): void
    {
        self::creating(function (CampaignEloquentModel $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid7();
            }
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Reusable list filter (BACKEND-PHP §4.1/§5.2 — single scope shared by
     * ListCampaignsHandler and the export controller). The `suspended`
     * status is applied via `onlyTrashed()` at the repository, the remaining
     * lifecycle values map onto `status`.
     *
     * @param  Builder<CampaignEloquentModel>  $query
     * @return Builder<CampaignEloquentModel>
     */
    public function scopeApplyFilters(Builder $query, CampaignFilterData $filters): Builder
    {
        return $query
            ->when($filters->search !== null, fn ($q) => $q->where(function ($w) use ($filters): void {
                $term = "%{$filters->search}%";
                $w->where('topic', 'like', $term)
                    ->orWhere('headline', 'like', $term);
            }))
            ->when(
                in_array($filters->status, ['draft', 'generating', 'ready', 'needs_review', 'published', 'scheduled'], true),
                fn ($q) => $q->where('status', $filters->status),
            )
            ->when(
                $filters->dateFrom !== null && $filters->dateTo !== null,
                fn ($q) => $q->whereBetween('created_at', [
                    CarbonImmutable::parse($filters->dateFrom)->startOfDay(),
                    CarbonImmutable::parse($filters->dateTo)->endOfDay(),
                ]),
            )
            ->when(
                $filters->dateFrom !== null && $filters->dateTo === null,
                fn ($q) => $q->where('created_at', '>=', CarbonImmutable::parse($filters->dateFrom)->startOfDay()),
            )
            ->when(
                $filters->dateTo !== null && $filters->dateFrom === null,
                fn ($q) => $q->where('created_at', '<=', CarbonImmutable::parse($filters->dateTo)->endOfDay()),
            );
    }

    /**
     * Permanent public URL for the stored R2 cover image, resolved through
     * the StoragePort adapter. Degrades to null on any R2 failure rather
     * than breaking the whole list/show render.
     *
     * @return Attribute<string|null, never>
     */
    protected function coverImageUrl(): Attribute
    {
        return Attribute::get(function (): ?string {
            $key = $this->cover_image_path;

            if ($key === null || $key === '') {
                return null;
            }

            try {
                return app(StoragePort::class)->publicUrl($key);
            } catch (Throwable) {
                return null;
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'business_goal' => CampaignBusinessGoal::class,
            'brand_voice' => CampaignBrandVoice::class,
            'funnel_stage' => CampaignFunnelStage::class,
            'platform' => CampaignPlatform::class,
            'ad_format' => CampaignAdFormat::class,
            'language' => CampaignLanguage::class,
            'status' => CampaignStatus::class,
            'scheduled_at' => 'datetime',
            'published_at' => 'datetime',
            'hashtags' => 'array',
            'lead_form_questions' => 'array',
            'targeting_suggestions' => 'array',
            'platforms' => 'array',
            'scores' => 'array',
            'audience_fit_score' => 'integer',
            'virality_score' => 'integer',
            'roi_potential_score' => 'integer',
            'lead_quality_score' => 'integer',
            'trend_relevance_score' => 'integer',
            'overall_score_avg' => 'integer',
            'all_scores_pass' => 'boolean',
            'iterations_required' => 'integer',
            'quality_warning' => 'boolean',
            'optimization_suggestions' => 'array',
            'research_sources' => 'array',
            'tavily_data_used' => 'array',
            'ai_detection_risk' => 'array',
            'created_by' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['topic', 'status', 'scheduled_at', 'published_at', 'overall_score_avg'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('campaigns');
    }

    protected static function newFactory(): CampaignFactory
    {
        return CampaignFactory::new();
    }
}
