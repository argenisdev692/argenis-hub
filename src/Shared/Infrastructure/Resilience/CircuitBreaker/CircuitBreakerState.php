<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Resilience\CircuitBreaker;

enum CircuitBreakerState: string
{
    case Closed = 'closed';      // healthy — calls flow through
    case Open = 'open';          // tripped — calls short-circuit (reject/fallback)
    case HalfOpen = 'half_open'; // cooldown elapsed — allow one trial call
}
