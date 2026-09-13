<?php

declare(strict_types=1);

namespace Modules\Cvs\Providers;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Cvs\Domain\Exceptions\CvNotFoundException;
use Modules\Cvs\Domain\Ports\CvRepositoryPort;
use Modules\Cvs\Domain\Ports\CvTextExtractorPort;
use Modules\Cvs\Infrastructure\Persistence\Repositories\EloquentCvRepository;
use Modules\Cvs\Infrastructure\Services\CvTextExtractor;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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

        $this->registerExceptionMapping();
    }

    /**
     * The domain's not-found becomes the framework's 404, so Inertia and JSON
     * callers get the same sanitized response as any unknown route (OWASP §10).
     */
    private function registerExceptionMapping(): void
    {
        $handler = $this->app->make(ExceptionHandler::class);

        if (! $handler instanceof Handler) {
            return;
        }

        $handler->map(
            CvNotFoundException::class,
            static fn (CvNotFoundException $exception): NotFoundHttpException => new NotFoundHttpException($exception->getMessage(), $exception),
        );
    }
}
