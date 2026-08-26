<?php

declare(strict_types=1);

namespace Modules\Company\Domain\Enums;

/**
 * The social channels the company publishes.
 *
 * Single source of truth for the channel ↔ column mapping. Before this enum the
 * six channels were spelled out independently in the Eloquent model, the audit
 * allowlist, the branding cache and every consumer — adding a seventh meant
 * finding all of them. Now the repository, the mapper and the DTOs iterate the
 * cases instead.
 */
enum SocialChannel: string
{
    case Facebook = 'facebook';
    case Github = 'github';
    case Instagram = 'instagram';
    case Linkedin = 'linkedin';
    case Tiktok = 'tiktok';
    case Twitter = 'twitter';

    /**
     * The `company_data` column backing this channel.
     */
    public function column(): string
    {
        return $this->value.'_link';
    }
}
