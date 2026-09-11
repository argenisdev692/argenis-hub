<?php

declare(strict_types=1);

namespace Modules\Invoices\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Invoices\Domain\Ports\InvoicePdfCachePort;
use Modules\Invoices\Domain\Ports\InvoicePdfRendererPort;
use Modules\Invoices\Domain\Ports\InvoiceRepositoryPort;
use Modules\Invoices\Infrastructure\Pdf\DomPdfInvoiceRenderer;
use Modules\Invoices\Infrastructure\Persistence\Repositories\EloquentInvoiceRepository;
use Modules\Invoices\Infrastructure\Queue\QueuedInvoicePdfCache;

final class InvoicesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(InvoiceRepositoryPort::class, EloquentInvoiceRepository::class);
        $this->app->bind(InvoicePdfRendererPort::class, DomPdfInvoiceRenderer::class);
        $this->app->bind(InvoicePdfCachePort::class, QueuedInvoicePdfCache::class);
    }

    public function boot(): void
    {
        $this->registerWebRoutes();
        $this->registerApiRoutes();
    }

    private function registerWebRoutes(): void
    {
        Route::middleware('web')->group(__DIR__.'/../Infrastructure/Routes/web.php');
    }

    private function registerApiRoutes(): void
    {
        Route::middleware('api')->prefix('api')->group(__DIR__.'/../Infrastructure/Routes/api.php');
    }
}
