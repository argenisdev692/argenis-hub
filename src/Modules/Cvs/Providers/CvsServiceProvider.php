<?php

declare(strict_types=1);

namespace Modules\Cvs\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Cvs\Domain\Ports\CvRepositoryPort;
use Modules\Cvs\Domain\Ports\CvTextExtractorPort;
use Modules\Cvs\Infrastructure\Persistence\Repositories\EloquentCvRepository;
use Modules\Cvs\Infrastructure\Services\CvTextExtractor;

final class CvsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CvRepositoryPort::class, EloquentCvRepository::class);
        $this->app->bind(CvTextExtractorPort::class, CvTextExtractor::class);
    }

    public function boot(): void
    {
        Route::middleware('web')->group(__DIR__.'/../Infrastructure/Routes/web.php');
        Route::middleware('api')->prefix('api')->group(__DIR__.'/../Infrastructure/Routes/api.php');
    }
}
