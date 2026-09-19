<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Exceptions;

use RuntimeException;

/**
 * Contact channel missing. Rendered as 404.
 */
final class ChannelNotFoundException extends RuntimeException
{
    public const string CODE = 'CHANNEL_NOT_FOUND';

    public function __construct(string $uuid)
    {
        parent::__construct("Channel {$uuid} not found.");
    }
}
