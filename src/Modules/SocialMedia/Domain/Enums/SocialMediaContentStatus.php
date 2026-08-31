<?php

declare(strict_types=1);

namespace Modules\SocialMedia\Domain\Enums;

enum SocialMediaContentStatus: string
{
    case Draft = 'draft';
    case Generating = 'generating';
    case Ready = 'ready';
    case NeedsReview = 'needs_review';
    case Published = 'published';
    case Scheduled = 'scheduled';
}
