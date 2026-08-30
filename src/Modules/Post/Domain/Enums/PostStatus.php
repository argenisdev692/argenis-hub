<?php

declare(strict_types=1);

namespace Modules\Post\Domain\Enums;

enum PostStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Scheduled = 'scheduled';
}
