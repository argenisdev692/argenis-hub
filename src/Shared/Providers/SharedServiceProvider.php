<?php

declare(strict_types=1);

namespace Shared\Providers;

use Dedoc\Scramble\Scramble;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\View as ViewFactory;
use Illuminate\Support\ServiceProvider;
use Shared\Domain\Ports\AuditPort;
use Shared\Domain\Ports\DocsVerificationPort;
use Shared\Domain\Ports\ExportPort;
use Shared\Domain\Ports\SpeechSynthesizerPort;
use Shared\Domain\Ports\StoragePort;
use Shared\Infrastructure\AI\AIClientInterface;
use Shared\Infrastructure\AI\LaravelAIAdapter;
use Shared\Infrastructure\Audit\SpatieActivityLogAdapter;
use Shared\Infrastructure\Company\CompanyProfile;
use Shared\Infrastructure\Console\Commands\PingRedisCommand;
use Shared\Infrastructure\Console\Commands\SyncR2CorsCommand;
use Shared\Infrastructure\Docs\Context7ClientInterface;
use Shared\Infrastructure\Docs\Context7DocsAdapter;
use Shared\Infrastructure\Export\SimpleExcelExportAdapter;
use Shared\Infrastructure\Mail\BrevoMailAdapter;
use Shared\Infrastructure\Mail\MailInterface;
use Shared\Infrastructure\Mail\ResendMailAdapter;
use Shared\Infrastructure\OpenApi\SpatieDataToSchema;
use Shared\Infrastructure\Research\TavilyClientInterface;
use Shared\Infrastructure\Research\TavilyResearchAdapter;
use Shared\Infrastructure\Resilience\CircuitBreaker\CircuitBreaker;
use Shared\Infrastructure\Resilience\CircuitBreaker\CircuitBreakerInterface;
use Shared\Infrastructure\Speech\ElevenLabsSpeechAdapter;
use Shared\Infrastructure\Storage\R2StorageAdapter;

/**
 * Wires the cross-cutting Shared ports to their concrete adapters.
 * Registered in bootstrap/providers.php.
 */
final class SharedServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AuditPort::class, SpatieActivityLogAdapter::class);
        $this->app->bind(StoragePort::class, R2StorageAdapter::class);
        // CSV/Excel handled here; PDF delegated to DomPdfExportAdapter (auto-resolved).
        $this->app->bind(ExportPort::class, SimpleExcelExportAdapter::class);
        $this->app->bind(AIClientInterface::class, LaravelAIAdapter::class);
        $this->app->bind(TavilyClientInterface::class, TavilyResearchAdapter::class);

        // Both docs contracts resolve to the same adapter — the Domain port for
        // grounded snippets, the client interface for callers that need the
        // resolved Context7 library id itself.
        $this->app->bind(DocsVerificationPort::class, Context7DocsAdapter::class);
        $this->app->bind(Context7ClientInterface::class, Context7DocsAdapter::class);
        $this->app->bind(SpeechSynthesizerPort::class, ElevenLabsSpeechAdapter::class);
        // Outbound mail provider is a config switch, not a code change — see the
        // "adapter" key in config/mail.php. Unknown values fall back to Brevo so a
        // typo in MAIL_ADAPTER degrades to the previous transport, not to no mail.
        $this->app->bind(MailInterface::class, static fn ($app): MailInterface => match ((string) config('mail.adapter')) {
            'resend' => $app->make(ResendMailAdapter::class),
            default => $app->make(BrevoMailAdapter::class),
        });

        // Per-request CSP nonce shared between the SecurityHeaders middleware
        // and the Blade root view (`{{ app('csp-nonce') }}`).
        $this->app->scoped('csp-nonce', static fn (): string => base64_encode(random_bytes(16)));

        $this->app->singleton(
            CircuitBreakerInterface::class,
            static fn ($app): CircuitBreaker => new CircuitBreaker($app->make(Cache::class)),
        );
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                PingRedisCommand::class,
                SyncR2CorsCommand::class,
            ]);
        }

        // Without this, every Spatie Data response is documented as an empty
        // schema — Scramble's own Laravel-Data support is a paid feature.
        Scramble::registerExtension(SpatieDataToSchema::class);

        // Inject company branding (base64 logos + contact block) into the shared
        // corporate PDF layout so every export controller stays branding-agnostic.
        ViewFactory::composer('exports.pdf.layout', static function (View $view): void {
            $view->with('company', CompanyProfile::pdfBranding());
        });
    }
}
