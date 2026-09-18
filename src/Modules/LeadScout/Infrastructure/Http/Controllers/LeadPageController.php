<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Bandeja Inertia shell (spec US-5, plan §5 `GET /lead-scout`). Rows load
 * through the JSON endpoints below (Pinia Colada); the page carries no
 * rows server-side, keeping the first paint light on a cloud database.
 */
final readonly class LeadPageController
{
    public function index(): InertiaResponse
    {
        return Inertia::render('lead-scout/Index');
    }
}
