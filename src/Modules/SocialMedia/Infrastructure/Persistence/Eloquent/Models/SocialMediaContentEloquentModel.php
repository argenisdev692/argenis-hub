<?php

declare(strict_types=1);

namespace Modules\SocialMedia\Infrastructure\Persistence\Eloquent\Models;

use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\SocialMediaContentFactory;
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
use Modules\SocialMedia\Application\DTOs\SocialMediaContentFilterData;
use Modules\SocialMedia\Domain\Enums\BrandVoice;
use Modules\SocialMedia\Domain\Enums\BusinessGoal;
use Modules\SocialMedia\Domain\Enums\ContentLanguage;
use Modules\SocialMedia\Domain\Enums\FunnelStage;
use Modules\SocialMedia\Domain\Enums\SocialMediaContentStatus;
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
 * @property BusinessGoal $business_goal
 * @property BrandVoice $brand_voice
 * @property FunnelStage $funnel_stage
 * @property ContentLanguage $language
 * @property string $provider
 * @property SocialMediaContentStatus $status
 * @property Carbon|null $scheduled_at
 * @property Carbon|null $published_at
 * @property string|null $headline
 * @property string|null $body
 * @property string|null $call_to_action
 * @property list<string>|null $hashtags
 * @property array<string, mixed>|null $platforms
 * @property string|null $cover_image_path
 * @property string|null $cover_image_prompt
 * @property array<string, mixed>|null $scores
 * @property int|null $human_writing_index
 * @property int|null $virality_score
 * @property int|null $engagement_score
 * @property int|null $roi_score
 * @property int|null $trend_alignment
 * @property int|null $overall_score_avg
 * @property bool $all_scores_pass
 * @property int|null $iterations_required
 * @property bool $quality_warning
 * @property string|null $quality_warning_message
 * @property array<string, mixed>|null $eeat_analysis
 * @property list<string>|null $optimization_suggestions
 * @property list<array<string, mixed>>|null $research_sources
 * @property list<string>|null $tavily_data_used
 * @property array<string, mixed>|null $ai_detection_risk
 * @property int|null $created_by
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string|null $cover_image_url
 * @property-read User|null $user
 *
 * @mixin \Eloquent
 */
#[Table('social_media_contents')]
#[Fillable([
    'uuid', 'niche', 'topic', 'angle', 'hook', 'key_trend', 'audience', 'business_goal',
    'brand_voice', 'funnel_stage', 'language', 'provider', 'status', 'scheduled_at', 'published_at',
    'headline', 'body', 'call_to_action', 'hashtags', 'platforms', 'cover_image_path',
    'cover_image_prompt', 'scores', 'human_writing_index', 'virality_score', 'engagement_score',
    'roi_score', 'trend_alignment', 'overall_score_avg', 'all_scores_pass', 'iterations_required',
    'quality_warning', 'quality_warning_message', 'eeat_analysis', 'optimization_suggestions',
    'research_sources', 'tavily_data_used', 'ai_detection_risk', 'created_by',
])]
final class SocialMediaContentEloquentModel extends Model
{
    /** @use HasFactory<SocialMediaContentFactory> */
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
        self::creating(function (SocialMediaContentEloquentModel $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid7();
            }
        });
    }

    /**
     * Owner / author of this package (BACKEND-PHP §4 — method MUST be `user()`,
     * even when the FK column is `created_by`).
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Reusable list filter (BACKEND-PHP §4.1/§5.2 — single scope shared by
     * ListSocialMediaContentHandler and the export controller). The
     * `suspended` status is applied via `onlyTrashed()` at the repository, the
     * remaining lifecycle values map onto `status`.
     *
     * @param  Builder<SocialMediaContentEloquentModel>  $query
     * @return Builder<SocialMediaContentEloquentModel>
     */
    public function scopeApplyFilters(Builder $query, SocialMediaContentFilterData $filters): Builder
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
     * Permanent public URL for the stored R2 cover image, resolved through the
     * StoragePort adapter. Degrades to null on any R2 failure rather than
     * breaking the whole list/show render.
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
            'business_goal' => BusinessGoal::class,
            'brand_voice' => BrandVoice::class,
            'funnel_stage' => FunnelStage::class,
            'language' => ContentLanguage::class,
            'status' => SocialMediaContentStatus::class,
            'scheduled_at' => 'datetime',
            'published_at' => 'datetime',
            'hashtags' => 'array',
            'platforms' => 'array',
            'scores' => 'array',
            'human_writing_index' => 'integer',
            'virality_score' => 'integer',
            'engagement_score' => 'integer',
            'roi_score' => 'integer',
            'trend_alignment' => 'integer',
            'overall_score_avg' => 'integer',
            'all_scores_pass' => 'boolean',
            'iterations_required' => 'integer',
            'quality_warning' => 'boolean',
            'eeat_analysis' => 'array',
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
            ->useLogName('social_media');
    }

    protected static function newFactory(): SocialMediaContentFactory
    {
        return SocialMediaContentFactory::new();
    }
}
