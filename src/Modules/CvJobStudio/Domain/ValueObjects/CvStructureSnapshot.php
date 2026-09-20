<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\ValueObjects;

/**
 * A parsed CV structure as the rewrite and tailoring use-cases need it. A
 * structure is unusable until the candidate confirms it (RK-6).
 */
final readonly class CvStructureSnapshot
{
    /**
     * @param  array<string, mixed>  $profileFacts
     * @param  array{entries: list<array{ordinal: int, organization: string|null, role_title: string|null}>, skills: list<array{canonical_name: string, evidence: string}>}  $snapshot
     * @param  list<string>  $protectedBullets  bullet text of entries marked protected
     * @param  array<string, mixed>  $document  the full structure (entries, bullets, skills) sent to the rewriter
     */
    public function __construct(
        public int $id,
        public string $uuid,
        public int $cvId,
        public bool $isConfirmed,
        public array $profileFacts,
        public array $snapshot,
        public array $protectedBullets,
        public array $document,
    ) {}
}
