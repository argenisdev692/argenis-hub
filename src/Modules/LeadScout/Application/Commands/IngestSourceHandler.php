<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository as Config;
use Modules\LeadScout\Domain\Entities\Source;
use Modules\LeadScout\Domain\Enums\CompanyOrigin;
use Modules\LeadScout\Domain\Enums\ContractType;
use Modules\LeadScout\Domain\Enums\FetchStatus;
use Modules\LeadScout\Domain\Enums\PostingStatus;
use Modules\LeadScout\Domain\Enums\RemoteMode;
use Modules\LeadScout\Domain\Enums\SourceStatus;
use Modules\LeadScout\Domain\Ports\CircuitBreakerPort;
use Modules\LeadScout\Domain\Ports\CompanyRepositoryPort;
use Modules\LeadScout\Domain\Ports\JobPostingRepositoryPort;
use Modules\LeadScout\Domain\Ports\JobSourcePort;
use Modules\LeadScout\Domain\Ports\PipelineLoggerPort;
use Modules\LeadScout\Domain\Ports\SourceRepositoryPort;
use Modules\LeadScout\Domain\Ports\SuppressionRepositoryPort;
use Modules\LeadScout\Domain\Services\SuppressionGate;
use Modules\LeadScout\Domain\ValueObjects\CanonicalDomain;
use Modules\LeadScout\Domain\ValueObjects\NewJobPosting;
use Modules\LeadScout\Domain\ValueObjects\PostingFingerprint;
use Modules\LeadScout\Domain\ValueObjects\RawPosting;
use Modules\LeadScout\Domain\ValueObjects\SkillTaxonomy;
use Throwable;

/**
 * Normalizes, relevance-filters, fingerprints, dedupes and links postings
 * (spec US-2, FR-4/FR-5). One source failing or out of quota never stops the
 * others (US-2 CA-4): the per-source breaker isolates it and the run records
 * the reason. Every call lands in `scout_fetch_attempts`.
 */
final readonly class IngestSourceHandler
{
    private const array EMPTY_COUNTS = ['created' => 0, 'linked' => 0, 'irrelevant' => 0, 'suppressed' => 0, 'expired' => 0];

    /**
     * @param  iterable<JobSourcePort>  $adapters  tagged `lead-scout.job-sources` in the provider
     */
    public function __construct(
        private SourceRepositoryPort $sources,
        private CompanyRepositoryPort $companies,
        private JobPostingRepositoryPort $postings,
        private SuppressionGate $gate,
        private SuppressionRepositoryPort $suppressions,
        private CircuitBreakerPort $breaker,
        private PipelineLoggerPort $log,
        private Config $config,
        private iterable $adapters,
    ) {}

    /**
     * @return array{created: int, linked: int, irrelevant: int, suppressed: int, expired: int}
     */
    public function handle(string $sourceUuid): array
    {
        $source = $this->sources->byUuid($sourceUuid);

        if ($source === null) {
            return self::EMPTY_COUNTS;
        }

        $started = microtime(true);
        $counts = self::EMPTY_COUNTS;

        try {
            $result = $this->breaker->call(
                "lead-scout:ingest:{$source->uuid}",
                function () use ($source, &$counts): array {
                    $this->ingest($source, $counts);

                    return $counts;
                },
                function (Throwable $e) use ($source): array {
                    $this->sources->setStatus($source, SourceStatus::Failing);

                    throw $e;
                },
            );
        } catch (Throwable $e) {
            $this->sources->recordAttempt($source, FetchStatus::Failed, self::elapsedMs($started), $e->getMessage());
            $this->sources->markRun($source, CarbonImmutable::now());

            $this->log->pipelineWarning('ingest_failed', [
                'source' => $source->name,
                'error' => mb_substr($e->getMessage(), 0, 200),
            ]);

            throw $e;
        }

        $this->sources->recordAttempt($source, FetchStatus::Ok, self::elapsedMs($started), null);
        $this->sources->markRun($source, CarbonImmutable::now());

        $this->log->pipeline('ingest_finished', ['source' => $source->name, ...$result]);

        return $result;
    }

    /**
     * @param  array{created: int, linked: int, irrelevant: int, suppressed: int, expired: int}  $counts
     */
    private function ingest(Source $source, array &$counts): void
    {
        $adapter = $this->adapterFor($source);

        if ($adapter === null) {
            return;
        }

        $latest = null;

        foreach ($adapter->fetchSince($source, $source->lastCursor) as $raw) {
            $this->ingestOne($source, $raw, $counts, $latest);
        }

        if ($latest !== null) {
            $this->sources->saveCursor($source, $latest);
        }
    }

    private function adapterFor(Source $source): ?JobSourcePort
    {
        foreach ($this->adapters as $adapter) {
            if ($adapter->supports($source)) {
                return $adapter;
            }
        }

        return null;
    }

    /**
     * @param  array{created: int, linked: int, irrelevant: int, suppressed: int, expired: int}  $counts
     */
    private function ingestOne(Source $source, RawPosting $raw, array &$counts, ?string &$latest): void
    {
        $publishedAt = self::parseDate($raw->publishedAt);

        if ($publishedAt !== null && ($latest === null || $publishedAt > $latest)) {
            $latest = $publishedAt;
        }

        if (! $this->isRelevant($raw)) {
            $counts['irrelevant']++;

            return;
        }

        $fingerprint = PostingFingerprint::make(
            $raw->companyName,
            $raw->title,
            $raw->location,
            $publishedAt !== null ? CarbonImmutable::parse($publishedAt) : CarbonImmutable::now(),
        )->value;

        $existingId = $this->postings->idByFingerprint($fingerprint);

        if ($existingId !== null) {
            $this->postings->attachSource($existingId, $source->id);
            $counts['linked']++;

            return;
        }

        $maxAge = CarbonImmutable::now()->subDays((int) $this->config->get('lead-scout.ingest.max_offer_age_days', 30));
        $isExpired = $publishedAt !== null && CarbonImmutable::parse($publishedAt)->lt($maxAge);

        $companyId = null;

        if (! $isExpired) {
            ['companyId' => $companyId, 'suppressed' => $suppressed] = $this->resolveCompany($raw);

            if ($suppressed) {
                $counts['suppressed']++;

                return;
            }
        }

        $posting = $this->postings->create(new NewJobPosting(
            companyId: $companyId,
            fingerprint: $fingerprint,
            companyName: mb_substr(trim($raw->companyName), 0, 255),
            title: mb_substr(trim($raw->title), 0, 255),
            location: $raw->location === null ? null : mb_substr(trim($raw->location), 0, 255),
            country: self::normalizeCountry($raw->country),
            remoteMode: self::normalizeRemote($raw->remoteMode),
            contractType: self::normalizeContract($raw->contractType),
            language: $raw->language === null ? null : mb_substr(mb_strtolower(trim($raw->language)), 0, 16),
            publishedAt: $publishedAt === null ? null : CarbonImmutable::parse($publishedAt),
            status: $isExpired ? PostingStatus::Expired : PostingStatus::Active,
            sourceUrl: mb_substr(trim($raw->sourceUrl), 0, 2048),
            companyUrl: $raw->companyUrl === null || trim($raw->companyUrl) === '' ? null : mb_substr(trim($raw->companyUrl), 0, 2048),
            bodyText: $raw->bodyText,
        ));

        $this->postings->attachSource($posting->id, $source->id);

        $counts[$isExpired ? 'expired' : 'created']++;
    }

    /**
     * Finds or creates the posting company. When the payload carries no
     * company URL the posting stays unresolved (`company_id` null) for T043.
     * Suppressed companies resolve to null with `suppressed: true` and the
     * posting is skipped entirely (spec FR-43).
     *
     * @return array{companyId: ?int, suppressed: bool}
     */
    private function resolveCompany(RawPosting $raw): array
    {
        $domain = null;

        if ($raw->companyUrl !== null && trim($raw->companyUrl) !== '') {
            try {
                $domain = CanonicalDomain::fromUrl(trim($raw->companyUrl))->value;
            } catch (\InvalidArgumentException) {
                $domain = null;
            }
        }

        $candidates = $this->suppressions->matching($domain, null, $raw->companyName);

        if ($this->gate->isSuppressed($domain, null, $raw->companyName, $candidates)) {
            return ['companyId' => null, 'suppressed' => true];
        }

        if ($domain === null) {
            return ['companyId' => null, 'suppressed' => false];
        }

        $company = $this->companies->byDomain($domain) ?? $this->companies->register(
            canonicalDomain: $domain,
            name: mb_substr(trim($raw->companyName), 0, 255),
            origin: CompanyOrigin::JobPosting,
            originRef: mb_substr(trim($raw->sourceUrl), 0, 255),
            country: self::normalizeCountry($raw->country) ?? 'ES',
        );

        return ['companyId' => $company->id, 'suppressed' => false];
    }

    private function isRelevant(RawPosting $raw): bool
    {
        $haystack = trim($raw->title.' '.($raw->bodyText ?? ''));

        if ($haystack === '') {
            return false;
        }

        $terms = [...array_keys(SkillTaxonomy::TERMS), ...(array) $this->config->get('lead-scout.ingest.expanded_terms', [])];

        foreach ($terms as $term) {
            if (SkillTaxonomy::mentions($haystack, (string) $term)) {
                return true;
            }
        }

        return false;
    }

    private static function elapsedMs(float $started): int
    {
        return (int) ((microtime(true) - $started) * 1000);
    }

    private static function parseDate(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse(trim($value))->toDateTimeString();
        } catch (\Exception) {
            return null;
        }
    }

    private static function normalizeCountry(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $clean = mb_strtoupper(trim($value));

        return strlen($clean) === 2 ? $clean : null;
    }

    private static function normalizeRemote(?string $value): RemoteMode
    {
        $lower = mb_strtolower(trim((string) $value));

        return match (true) {
            str_contains($lower, 'remot') => RemoteMode::Remote,
            str_contains($lower, 'hybrid') || str_contains($lower, 'hibrid') => RemoteMode::Hybrid,
            str_contains($lower, 'onsite') || $lower === 'on-site' || str_contains($lower, 'presencial') => RemoteMode::Onsite,
            default => RemoteMode::Unknown,
        };
    }

    private static function normalizeContract(?string $value): ContractType
    {
        $lower = mb_strtolower(trim((string) $value));

        return match (true) {
            str_contains($lower, 'freelance') || str_contains($lower, 'contract') || str_contains($lower, 'autonomo') || str_contains($lower, 'autónomo') => ContractType::Freelance,
            str_contains($lower, 'employ') || str_contains($lower, 'empleo') || str_contains($lower, 'fijo') || str_contains($lower, 'indefinido') => ContractType::Employment,
            default => ContractType::Unknown,
        };
    }
}
