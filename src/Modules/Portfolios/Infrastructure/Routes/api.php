<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Portfolios\Infrastructure\Http\Controllers\Api\PublicPortfolioController;

/*
|--------------------------------------------------------------------------
| Portfolios module — API routes
|--------------------------------------------------------------------------
|
| Two public, unauthenticated GETs consumed by the landing page and any
| standalone Astro site: the JSON showcase feed and its CSV / Excel / PDF
| export. The export carries its own, stricter rate limiter because a PDF
| render is far more expensive than a cached JSON read (OWASP §14).
|
| Mounted under the api prefix by PortfoliosServiceProvider so Scramble picks
| it up with the rest of the API surface (config('scramble.api_path')).
|
*/

Route::prefix('public/portfolios')
    ->name('api.public.portfolios.')
    ->group(function (): void {
        Route::middleware('throttle:public-portfolios-export')
            ->get('/export', [PublicPortfolioController::class, 'export'])
            ->name('export');

        Route::middleware('throttle:public-portfolios')
            ->get('/', [PublicPortfolioController::class, 'index'])
            ->name('index');
    });
