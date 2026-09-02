<?php

declare(strict_types=1);

namespace Modules\Products\Application\Support;

use Illuminate\Support\Str;
use Modules\Products\Infrastructure\Persistence\Eloquent\Models\ProductEloquentModel;

/**
 * Slugs are derived from the title, never accepted from the client, and stay
 * unique across trashed rows too — `products.slug` carries a UNIQUE index that
 * soft-deleted rows still occupy.
 */
final readonly class ProductSlug
{
    #[\NoDiscard]
    public static function uniqueFor(string $title, ?int $exceptId = null): string
    {
        $base = Str::slug($title);
        if ($base === '') {
            $base = 'product';
        }

        $slug = $base;
        $suffix = 2;

        while (self::taken($slug, $exceptId)) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    private static function taken(string $slug, ?int $exceptId): bool
    {
        return ProductEloquentModel::withTrashed()
            ->where('slug', $slug)
            ->when($exceptId !== null, fn ($q) => $q->whereKeyNot($exceptId))
            ->exists();
    }
}
