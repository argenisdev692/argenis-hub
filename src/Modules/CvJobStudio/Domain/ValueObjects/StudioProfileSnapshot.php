<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\ValueObjects;

/** The gate inputs and rules of one profile, as the use-cases read them. */
final readonly class StudioProfileSnapshot
{
    /**
     * @param  list<string>  $acceptedRemoteScopes
     * @param  list<string>  $stackMust
     * @param  list<string>  $stackReject
     * @param  array<string, int>  $skillFloors  per-skill defensibility, read by the shortlist filter
     * @param  array<string, mixed>  $rules
     */
    public function __construct(
        public int $id,
        public string $uuid,
        public array $acceptedRemoteScopes,
        public array $stackMust,
        public array $stackReject,
        public array $skillFloors,
        public array $rules,
    ) {}
}
