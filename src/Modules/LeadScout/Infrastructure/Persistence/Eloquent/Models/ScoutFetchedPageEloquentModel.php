<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\LeadScout\Domain\Enums\PageType;

/**
 * Fetched public page. `content_markdown` is pruned after 30 days
 * (hash + forms_summary kept); evidence lives on in `scout_signals`.
 *
 * @property int $id
 * @property string $uuid
 * @property-read ScoutCompanyEloquentModel $company
 *
 * @mixin \Eloquent
 *
 * @internal
 */
#[Table('scout_fetched_pages')]
#[Fillable([
    'uuid',
    'company_id',
    'url',
    'page_type',
    'content_markdown',
    'content_hash',
    'content_pruned_at',
    'forms_summary',
    'fetched_at',
])]
final class ScoutFetchedPageEloquentModel extends Model
{
    use HasUuids;

    /** @var list<string> */
    protected $hidden = ['id'];

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'page_type' => PageType::class,
            'forms_summary' => 'array',
            'content_pruned_at' => 'datetime',
            'fetched_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ScoutCompanyEloquentModel, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(ScoutCompanyEloquentModel::class, 'company_id');
    }
}
