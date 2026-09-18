<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\Tag;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\LeadScout\Application\DTOs\RawPostingData;
use Modules\LeadScout\Domain\Enums\CompanyOrigin;
use Modules\LeadScout\Domain\Enums\ContractType;
use Modules\LeadScout\Domain\Enums\FetchMethod;
use Modules\LeadScout\Domain\Enums\FetchStatus;
use Modules\LeadScout\Domain\Enums\PostingStatus;
use Modules\LeadScout\Domain\Enums\RemoteMode;
use Modules\LeadScout\Domain\Enums\SourceStatus;
use Modules\LeadScout\Domain\Enums\SourceType;
use Modules\LeadScout\Domain\Ports\CompanyRepositoryPort;
use Modules\LeadScout\Domain\Ports\JobPostingRepositoryPort;
use Modules\LeadScout\Domain\Ports\JobSourcePort;
use Modules\LeadScout\Domain\Services\SuppressionGate;
use Modules\LeadScout\Domain\ValueObjects\CanonicalDomain;
use Modules\LeadScout\Domain\ValueObjects\PostingFingerprint;
use Modules\LeadScout\Domain\ValueObjects\SkillTaxonomy;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutFetchAttemptEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutSourceEloquentModel;
use Shared\Infrastructure\Resilience\CircuitBreaker\CircuitBreakerInterface;
use Throwable;

/**
 * Normalizes, relevance-filters, fingerprints, dedupes and links postings
 * (spec US-2, FR-4/FR-5). One source failing or out of quota never stops the
 * others (US-2 CA-4): the per-source breaker isolates it and the run records
 * the reason. Every call lands in `scout_fetch_attempts`.
 *
 * @return array{created: int, linked: int, irrelevant: int, suppressed: int, expired: int}
 */
final readonly class IngestSourceHandler
{
    /**
     * @param  iterable<JobSourcePort>  $adapters
     */
    public function __construct(
        private CompanyRepositoryPort $companies,
        private JobPostingRepositoryPort $postings,
        private SuppressionGate $gate,
        private CircuitBreakerInterface $breaker,
        #[Tag('lead-scout.job-sources')] private iterable $adapters,
    ) {}

    public function handle(ScoutSourceEloquentModel $source): array
    {
        $started = microtime(true);
        $counts = ['created' => 0, 'linked' => 0, 'irrelevant' => 0, 'suppressed' => 0, 'expired' => 0];

        try {
            $result = $this->breaker->call(
                "lead-scout:ingest:{$source->uuid}",
                function () use ($source, &$counts): array {
                    foreach ($this->ingest($source, $counts) as $ignored) {
                        // Generator drained for its side effects.
                    }

                    return $counts;
                },
                function (Throwable $e) use ($source): array {
                    $this->markSource($source, SourceStatus::Failing);

                    throw $e;
                },
            );
        } catch (Throwable $e) {
            $this->recordAttempt($source, FetchStatus::Failed, $started, $e->getMessage());
            $this->touchRun($source);

            Log::warning('lead-scout.ingest_failed', [
                'source' => $source->name,
                'error' => mb_substr($e->getMessage(), 0, 200),
            ]);

            throw $e;
        }

        $this->recordAttempt($source, FetchStatus::Ok, $started, null);
        $this->touchRun($source);

        Log::info('lead-scout.ingest_finished', [
            'source' => $source->name,
            'created' => $result['created'],
            'linked' => $result['linked'],
            'irrelevant' => $result['irrelevant'],
            'suppressed' => $result['suppressed'],
            'expired' => $result['expired'],
        ]);

        return $result;
    }

    /**
     * @param  array{created: int, linked: int, irrelevant: int, suppressed: int, expired: int}  $counts
     * @return iterable<null>
     */
    private function ingest(ScoutSourceEloquentModel $source, array &$counts): iterable
    {
        $adapter = $this->adapterFor($source);

        if ($adapter === null) {
            return;
        }

        $latest = null;

        foreach ($adapter->fetchSince($source, $source->last_cursor) as $raw) {
            $this->ingestOne($source, $raw, $counts, $latest);

            yield null;
        }

        if ($latest !== null) {
            $source->update(['last_cursor' => $latest]);
        }
    }

    private function adapterFor(ScoutSourceEloquentModel $source): ?JobSourcePort
    {
        foreach ($this->adapters as $adapter) {
            if ($adapter->supports($source)) {
                return $adapter;
            }
        }

        return null;
    }

    private function ingestOne(
        ScoutSourceEloquentModel $source,
        RawPostingData $raw,
        array &$counts,
        ?string &$latest,
    ): void {
        $publishedAt = self::parseDate($raw->publishedAt);

        if ($publishedAt !== null && ($latest === null || $publishedAt > $latest)) {
            $latest = $publishedAt;
        }

        if (! self::isRelevant($raw)) {
            $counts['irrelevant']++;

            return;
        }

        $fingerprint = PostingFingerprint::make(
            $raw->companyName,
            $raw->title,
            $raw->location,
            $publishedAt !== null ? CarbonImmutable::parse($publishedAt) : CarbonImmutable::now(),
        )->value;

        $existing = $this->postings->findByFingerprint($fingerprint);

        if ($existing !== null) {
            $this->postings->attachSource($existing, $source->id);
            $counts['linked']++;

            return;
        }

        $maxAge = CarbonImmutable::now()->subDays((int) config('lead-scout.ingest.max_offer_age_days', 30));
        $isExpired = $publishedAt !== null && CarbonImmutable::parse($publishedAt)->lt($maxAge);

        $companyId = null;

        if (! $isExpired) {
            ['companyId' => $companyId, 'suppressed' => $suppressed] = $this->resolveCompany($raw);

            if ($suppressed) {
                $counts['suppressed']++;

                return;
            }
        }

        $posting = $this->postings->create([
            'company_id' => $companyId,
            'fingerprint' => $fingerprint,
            'company_name' => mb_substr(trim($raw->companyName), 0, 255),
            'title' => mb_substr(trim($raw->title), 0, 255),
            'location' => $raw->location === null ? null : mb_substr(trim($raw->location), 0, 255),
            'country' => self::normalizeCountry($raw->country),
            'remote_mode' => self::normalizeRemote($raw->remoteMode)->value,
            'contract_type' => self::normalizeContract($raw->contractType)->value,
            'language' => $raw->language === null ? null : mb_substr(mb_strtolower(trim($raw->language)), 0, 16),
            'published_at' => $publishedAt,
            'status' => $isExpired ? PostingStatus::Expired->value : PostingStatus::Active->value,
            'source_url' => mb_substr(trim($raw->sourceUrl), 0, 2048),
            'company_url' => $raw->companyUrl === null || trim($raw->companyUrl) === '' ? null : mb_substr(trim($raw->companyUrl), 0, 2048),
            'body_text' => $raw->bodyText,
        ]);

        $this->postings->attachSource($posting, $source->id);

        if ($isExpired) {
            $counts['expired']++;
        } else {
            $counts['created']++;
        }
    }

    /**
     * Finds or creates the posting company. When the payload carries no
     * company URL the posting stays unresolved (`company_id` null) for T043.
     * Suppressed companies resolve to null with `suppressed: true` and the
     * posting is skipped entirely (spec FR-43).
     *
     * @return array{companyId: ?int, suppressed: bool}
     */
    private function resolveCompany(RawPostingData $raw): array
    {
        $domain = null;

        if ($raw->companyUrl !== null && trim($raw->companyUrl) !== '') {
            try {
                $domain = CanonicalDomain::fromUrl(trim($raw->companyUrl))->value;
            } catch (\InvalidArgumentException) {
                $domain = null;
            }
        }

        $candidates = $this->companies->suppressionsMatching($domain, null, $raw->companyName)
            ->map(static fn ($row): array => [
                'canonical_domain' => $row->canonical_domain,
                'tax_id' => $row->tax_id,
                'name' => $row->name,
            ])
            ->all();

        if ($this->gate->isSuppressed($domain, null, $raw->companyName, $candidates)) {
            return ['companyId' => null, 'suppressed' => true];
        }

        if ($domain === null) {
            return ['companyId' => null, 'suppressed' => false];
        }

        $company = $this->companies->findByDomain($domain);

        if ($company !== null) {
            return ['companyId' => $company->id, 'suppressed' => false];
        }

        $created = $this->companies->create([
            'canonical_domain' => $domain,
            'name' => mb_substr(trim($raw->companyName), 0, 255),
            'country' => self::normalizeCountry($raw->country) ?? 'ES',
            'origin' => CompanyOrigin::JobPosting->value,
            'origin_ref' => mb_substr(trim($raw->sourceUrl), 0, 255),
        ]);

        return ['companyId' => $created->id, 'suppressed' => false];
    }

    #[\NoDiscard('Relevance decision must be captured')]
    public static function isRelevant(RawPostingData $raw): bool
    {
        $haystack = trim($raw->title.' '.($raw->bodyText ?? ''));

        if ($haystack === '') {
            return false;
        }

        foreach ([...array_keys(SkillTaxonomy::TERMS), ...(array) config('lead-scout.ingest.expanded_terms', [])] as $term) {
            if (SkillTaxonomy::mentions($haystack, (string) $term)) {
                return true;
            }
        }

        return false;
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

    private function recordAttempt(
        ScoutSourceEloquentModel $source,
        FetchStatus $status,
        float $started,
        ?string $error,
    ): void {
        ScoutFetchAttemptEloquentModel::query()->create([
            'source_id' => $source->id,
            'method' => $source->type === SourceType::Rss ? FetchMethod::Rss->value : FetchMethod::Api->value,
            'status' => $status->value,
            'duration_ms' => (int) ((microtime(true) - $started) * 1000),
            'error' => $error === null ? null : mb_substr($error, 0, 255),
        ]);
    }

    private function touchRun(ScoutSourceEloquentModel $source): void
    {
        DB::transaction(static function () use ($source): void {
            $source->update(['last_run_at' => now()]);
        });
    }

    private function markSource(ScoutSourceEloquentModel $source, SourceStatus $status): void
    {
        $source->update(['status' => $status->value]);
    }
}
