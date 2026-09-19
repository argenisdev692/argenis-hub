<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\Commands;

use Carbon\CarbonImmutable;
use Modules\LeadScout\Application\DTOs\UpdateSourceData;
use Modules\LeadScout\Domain\Entities\Source;
use Modules\LeadScout\Domain\Enums\SourceStatus;
use Modules\LeadScout\Domain\Exceptions\SourceNotFoundException;
use Modules\LeadScout\Domain\Exceptions\SourceTermsNotReviewedException;
use Modules\LeadScout\Domain\Ports\SourceRepositoryPort;

/**
 * Source configuration (spec FR-2, FR-13): a source is never active without
 * reviewed terms. Fields left out of the request keep their stored value.
 */
final readonly class UpdateSourceHandler
{
    public function __construct(private SourceRepositoryPort $sources) {}

    public function handle(string $sourceUuid, UpdateSourceData $data): Source
    {
        $source = $this->sources->byUuid($sourceUuid) ?? throw new SourceNotFoundException($sourceUuid);

        $status = $data->status === null ? $source->status : SourceStatus::from($data->status);
        $termsReviewedAt = $data->termsReviewedAt === null
            ? $source->termsReviewedAt
            : CarbonImmutable::parse($data->termsReviewedAt);

        if ($status === SourceStatus::Active && $termsReviewedAt === null) {
            throw new SourceTermsNotReviewedException;
        }

        return $this->sources->configure(
            $source,
            $status,
            $data->frequencyMinutes ?? $source->frequencyMinutes,
            $termsReviewedAt,
        );
    }
}
