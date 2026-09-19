<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\LeadScout\Domain\Enums\AiPurpose;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Default + fallback provider/model per AI purpose, editable from the web
 * (spec US-10, FR-22). Seeded from `.env`, never holding secrets.
 *
 * @property int $id
 *
 * @mixin \Eloquent
 *
 * @internal
 */
#[Table('scout_ai_settings')]
#[Fillable([
    'purpose',
    'provider',
    'model',
    'fallback_provider',
    'fallback_model',
])]
final class ScoutAiSettingEloquentModel extends Model
{
    use LogsActivity;

    /** @var list<string> */
    protected $hidden = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['purpose' => AiPurpose::class];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['purpose', 'provider', 'model', 'fallback_provider', 'fallback_model'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('lead-scout.ai-setting');
    }
}
