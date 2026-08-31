<?php

declare(strict_types=1);

namespace Modules\SocialMedia\Domain\Enums;

enum BrandVoice: string
{
    case Professional = 'professional';
    case Conversational = 'conversational';
    case Trendy = 'trendy';
    case Inspirational = 'inspirational';
    case Humorous = 'humorous';
}
