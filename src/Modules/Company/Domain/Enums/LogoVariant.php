<?php

declare(strict_types=1);

namespace Modules\Company\Domain\Enums;

/**
 * The three brand marks the company uploads.
 *
 * `Logo` is the full lockup on light backgrounds, `LogoWhite` the same lockup
 * reversed for dark/coloured bands (the PDF header, dark-mode email shells) and
 * `Mark` the standalone glyph used where the lockup will not fit — favicons,
 * avatars, the collapsed sidebar.
 *
 * Like {@see SocialChannel} this owns the variant ↔ column mapping so upload
 * validation, storage paths and persistence all read from one place.
 */
enum LogoVariant: string
{
    case Logo = 'logo';
    case LogoWhite = 'logo_white';
    case Mark = 'mark';

    /**
     * The `company_data` column holding this variant's stored object key.
     */
    public function column(): string
    {
        return $this->value.'_path';
    }

    /**
     * Cloud-storage directory for this variant.
     */
    public function directory(): string
    {
        return 'company/logos/'.$this->value;
    }
}
