<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Providers;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\CvJobStudio\Domain\Exceptions\PostingNotFoundException;
use Modules\CvJobStudio\Domain\Exceptions\ProfileGateIncompleteException;
use Modules\CvJobStudio\Domain\Exceptions\StructureNotConfirmedException;
use Modules\CvJobStudio\Domain\Exceptions\VersionNotFoundException;
use Modules\CvJobStudio\Domain\Ports\CvJudgePort;
use Modules\CvJobStudio\Domain\Ports\CvRewriterPort;
use Modules\CvJobStudio\Domain\Ports\CvSourcePort;
use Modules\CvJobStudio\Domain\Ports\CvStructureParserPort;
use Modules\CvJobStudio\Domain\Ports\EmbeddingPort;
use Modules\CvJobStudio\Domain\Ports\ProjectSourcePort;
use Modules\CvJobStudio\Domain\Ports\RequirementExtractorPort;
use Modules\CvJobStudio\Domain\Ports\SimilaritySearchPort;
use Modules\CvJobStudio\Domain\Ports\SpendGuardPort;
use Modules\CvJobStudio\Domain\Ports\StudioPostingRepositoryPort;
use Modules\CvJobStudio\Domain\Ports\StudioProfileRepositoryPort;
use Modules\CvJobStudio\Domain\Ports\StudioScoreRepositoryPort;
use Modules\CvJobStudio\Domain\Ports\TransactionPort;
use Modules\CvJobStudio\Infrastructure\Ai\LaravelAiCvJudge;
use Modules\CvJobStudio\Infrastructure\Ai\LaravelAiCvRewriter;
use Modules\CvJobStudio\Infrastructure\Ai\LaravelAiCvStructureParser;
use Modules\CvJobStudio\Infrastructure\Ai\LaravelAiRequirementExtractor;
use Modules\CvJobStudio\Infrastructure\Budgets\StudioBudgetLedger;
use Modules\CvJobStudio\Infrastructure\Console\Commands\StudioAgeOutcomesCommand;
use Modules\CvJobStudio\Infrastructure\Console\Commands\StudioImportEscoSkillsCommand;
use Modules\CvJobStudio\Infrastructure\Console\Commands\StudioReembedCommand;
use Modules\CvJobStudio\Infrastructure\Console\Commands\StudioRefreshVocabularyCommand;
use Modules\CvJobStudio\Infrastructure\Console\Commands\StudioRescoreCommand;
use Modules\CvJobStudio\Infrastructure\Console\Commands\StudioRunCommand;
use Modules\CvJobStudio\Infrastructure\Cvs\EloquentCvSource;
use Modules\CvJobStudio\Infrastructure\Embeddings\InMemorySimilaritySearch;
use Modules\CvJobStudio\Infrastructure\Embeddings\LaravelAiEmbeddingAdapter;
use Modules\CvJobStudio\Infrastructure\Embeddings\PgVectorSimilaritySearch;
use Modules\CvJobStudio\Infrastructure\Fetching\DirectHttpPostingFetcher;
use Modules\CvJobStudio\Infrastructure\Fetching\FirecrawlPostingFetcher;
use Modules\CvJobStudio\Infrastructure\Fetching\OutboundUrlGuard;
use Modules\CvJobStudio\Infrastructure\Fetching\PostingFetchLadder;
use Modules\CvJobStudio\Infrastructure\Persistence\Repositories\EloquentStudioPostingRepository;
use Modules\CvJobStudio\Infrastructure\Persistence\Repositories\EloquentStudioProfileRepository;
use Modules\CvJobStudio\Infrastructure\Persistence\Repositories\EloquentStudioScoreRepository;
use Modules\CvJobStudio\Infrastructure\Persistence\Transactions\StudioTransactionRunner;
use Modules\CvJobStudio\Infrastructure\Projects\CompositeProjectSource;
use Modules\CvJobStudio\Infrastructure\Sources\AdzunaSource;
use Modules\CvJobStudio\Infrastructure\Sources\ArbeitnowSource;
use Modules\CvJobStudio\Infrastructure\Sources\AshbyBoardSource;
use Modules\CvJobStudio\Infrastructure\Sources\GreenhouseBoardSource;
use Modules\CvJobStudio\Infrastructure\Sources\HimalayasSource;
use Modules\CvJobStudio\Infrastructure\Sources\ItJobsApiSource;
use Modules\CvJobStudio\Infrastructure\Sources\JobicySource;
use Modules\CvJobStudio\Infrastructure\Sources\LeverBoardSource;
use Modules\CvJobStudio\Infrastructure\Sources\RecruiteeBoardSource;
use Modules\CvJobStudio\Infrastructure\Sources\RemoteOkSource;
use Modules\CvJobStudio\Infrastructure\Sources\RemotiveSource;
use Modules\CvJobStudio\Infrastructure\Sources\RssFeedSource;
use Modules\CvJobStudio\Infrastructure\Sources\SourceResolver;
use Modules\CvJobStudio\Infrastructure\Sources\TavilySearchSource;
use Modules\CvJobStudio\Infrastructure\Sources\TeamtailorSitemapSource;
use Modules\CvJobStudio\Infrastructure\Sources\WorkableBoardSource;
use Shared\Domain\Ports\WordExportPort;
use Shared\Infrastructure\Export\PhpWordExportAdapter;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class CvJobStudioServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(StudioPostingRepositoryPort::class, EloquentStudioPostingRepository::class);
        $this->app->bind(StudioScoreRepositoryPort::class, EloquentStudioScoreRepository::class);
        $this->app->bind(StudioProfileRepositoryPort::class, EloquentStudioProfileRepository::class);
        $this->app->bind(TransactionPort::class, StudioTransactionRunner::class);
        $this->app->bind(CvSourcePort::class, EloquentCvSource::class);
        $this->app->bind(ProjectSourcePort::class, CompositeProjectSource::class);
        $this->app->bind(RequirementExtractorPort::class, LaravelAiRequirementExtractor::class);
        $this->app->bind(CvJudgePort::class, LaravelAiCvJudge::class);
        $this->app->bind(CvRewriterPort::class, LaravelAiCvRewriter::class);
        $this->app->bind(CvStructureParserPort::class, LaravelAiCvStructureParser::class);
        $this->app->bind(EmbeddingPort::class, LaravelAiEmbeddingAdapter::class);
        $this->app->bind(SpendGuardPort::class, StudioBudgetLedger::class);
        $this->app->bind(WordExportPort::class, PhpWordExportAdapter::class);

        $this->app->bind(SimilaritySearchPort::class, static fn (): SimilaritySearchPort => DB::getDriverName() === 'pgsql'
            ? app(PgVectorSimilaritySearch::class)
            : app(InMemorySimilaritySearch::class));

        // Autowiring would build the guard with an EMPTY never-fetch list and
        // let every link_only host (linkedin.com, indeed.com…) be fetched.
        $this->app->bind(OutboundUrlGuard::class, static fn (): OutboundUrlGuard => new OutboundUrlGuard(
            (array) config('cv-job-studio.never_fetch_hosts', []),
        ));

        $this->app->bind(PostingFetchLadder::class, static fn (): PostingFetchLadder => new PostingFetchLadder([
            app(DirectHttpPostingFetcher::class),
            app(FirecrawlPostingFetcher::class),
        ]));

        $this->app->bind(SourceResolver::class, static fn (): SourceResolver => new SourceResolver([
            app(GreenhouseBoardSource::class),
            app(LeverBoardSource::class),
            app(AshbyBoardSource::class),
            app(RecruiteeBoardSource::class),
            app(WorkableBoardSource::class),
            app(TeamtailorSitemapSource::class),
            app(ArbeitnowSource::class),
            app(RssFeedSource::class),
            app(ItJobsApiSource::class),
            app(RemoteOkSource::class),
            app(RemotiveSource::class),
            app(HimalayasSource::class),
            app(JobicySource::class),
            app(AdzunaSource::class),
            app(TavilySearchSource::class),
        ]));
    }

    public function boot(): void
    {
        $this->registerWebRoutes();
        $this->registerApiRoutes();

        $this->commands([
            StudioRunCommand::class,
            StudioRescoreCommand::class,
            StudioRefreshVocabularyCommand::class,
            StudioReembedCommand::class,
            StudioAgeOutcomesCommand::class,
            StudioImportEscoSkillsCommand::class,
        ]);

        $this->registerExceptionMapping();
    }

    private function registerWebRoutes(): void
    {
        Route::middleware('web')->group(__DIR__.'/../Infrastructure/Routes/web.php');
    }

    private function registerApiRoutes(): void
    {
        Route::middleware('api')->prefix('api')->group(__DIR__.'/../Infrastructure/Routes/api.php');
    }

    private function registerExceptionMapping(): void
    {
        $handler = $this->app->make(ExceptionHandler::class);

        if (! $handler instanceof Handler) {
            return;
        }

        $handler->map(
            PostingNotFoundException::class,
            static fn (PostingNotFoundException $exception): NotFoundHttpException => new NotFoundHttpException($exception->getMessage(), $exception),
        );

        $handler->map(
            VersionNotFoundException::class,
            static fn (VersionNotFoundException $exception): NotFoundHttpException => new NotFoundHttpException($exception->getMessage(), $exception),
        );

        $handler->map(
            StructureNotConfirmedException::class,
            static fn (StructureNotConfirmedException $exception): UnprocessableEntityHttpException => new UnprocessableEntityHttpException($exception->getMessage(), $exception),
        );

        $handler->map(
            ProfileGateIncompleteException::class,
            static fn (ProfileGateIncompleteException $exception): UnprocessableEntityHttpException => new UnprocessableEntityHttpException($exception->getMessage(), $exception),
        );
    }
}
