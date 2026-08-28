<?php

declare(strict_types=1);

namespace Modules\Portfolios\Infrastructure\Persistence\Eloquent\Models;

use Database\Factories\PortfolioMediaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Modules\Portfolios\Infrastructure\Cache\PortfolioPublicFeedCache;
use Shared\Domain\Ports\StoragePort;

/**
 * A single gallery image belonging to a {@see PortfolioEloquentModel}.
 *
 * Not an aggregate root: it has no admin list, no export and no audit trail of
 * its own — the parent portfolio owns its whole media set and replaces it
 * wholesale on write. Only `path` (an R2 object key) and `sort_order` matter.
 *
 * @property int $id
 * @property string $uuid
 * @property int $portfolio_id
 * @property string $path
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read string $url
 * @property-read PortfolioEloquentModel $portfolio
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PortfolioMediaEloquentModel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PortfolioMediaEloquentModel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PortfolioMediaEloquentModel query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PortfolioMediaEloquentModel withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PortfolioMediaEloquentModel withoutTrashed()
 * @method static PortfolioMediaFactory factory($count = null, $state = [])
 *
 * @mixin \Eloquent
 */
#[Fillable(['uuid', 'portfolio_id', 'path', 'sort_order'])]
#[Hidden(['id'])]
final class PortfolioMediaEloquentModel extends Model
{
    /** @use HasFactory<PortfolioMediaFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'portfolio_media';

    protected static function newFactory(): PortfolioMediaFactory
    {
        return PortfolioMediaFactory::new();
    }

    protected static function booted(): void
    {
        self::creating(function (self $media): void {
            if (empty($media->uuid)) {
                $media->uuid = (string) Str::uuid7();
            }
        });

        // A gallery change is a feed change for the parent portfolio.
        self::saved(static function (): void {
            PortfolioPublicFeedCache::flush();
        });
        self::deleted(static function (): void {
            PortfolioPublicFeedCache::flush();
        });
    }

    /** @return BelongsTo<PortfolioEloquentModel, $this> */
    public function portfolio(): BelongsTo
    {
        return $this->belongsTo(PortfolioEloquentModel::class, 'portfolio_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /**
     * Absolute public URL for this image, resolved through the Shared storage
     * port from the stored R2 object key.
     */
    protected function url(): Attribute
    {
        return Attribute::get(fn (): string => app(StoragePort::class)->publicUrl($this->path));
    }
}
