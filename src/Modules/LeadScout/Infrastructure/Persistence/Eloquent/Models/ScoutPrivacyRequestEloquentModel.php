<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\LeadScout\Domain\Enums\PrivacyRequestOutcome;
use Modules\LeadScout\Domain\Enums\PrivacyRequestType;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * GDPR request ledger (spec FR-29): type, dates and outcome only — personal
 * data is never copied into the log or the row.
 *
 * @property int $id
 *
 * @mixin \Eloquent
 *
 * @internal
 */
#[Table('scout_privacy_requests')]
#[Fillable([
    'subject_ref',
    'request_type',
    'received_at',
    'resolved_at',
    'outcome',
])]
final class ScoutPrivacyRequestEloquentModel extends Model
{
    use LogsActivity;

    /** @var list<string> */
    protected $hidden = ['id', 'subject_ref'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'request_type' => PrivacyRequestType::class,
            'received_at' => 'datetime',
            'resolved_at' => 'datetime',
            'outcome' => PrivacyRequestOutcome::class,
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['request_type', 'received_at', 'resolved_at', 'outcome'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('lead-scout.privacy');
    }
}
