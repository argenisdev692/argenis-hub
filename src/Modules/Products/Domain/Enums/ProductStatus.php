<?php

declare(strict_types=1);

namespace Modules\Products\Domain\Enums;

/**
 * Only `Published` products are offered in the invoice line-item picker.
 */
enum ProductStatus: string
{
    case Draft = 'DRAFT';
    case Published = 'PUBLISHED';
    case Archived = 'ARCHIVED';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
