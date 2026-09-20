<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\Ports;

interface CvTailorPort
{
    /**
     * Re-orders and re-words a confirmed CV toward a posting's vocabulary
     * without introducing skills absent from the snapshot (OWASP LLM01).
     *
     * @param  array{entries: list<array{ordinal: int, organization: string|null, role_title: string|null}>, skills: list<array{canonical_name: string, evidence: string}>}  $cvSnapshot
     * @param  list<string>  $protectedBlock
     * @param  list<array{name: string, tag: string}>  $requirements
     * @return array{summary: string, skills: list<mixed>, provenance: list<mixed>}
     */
    public function tailor(array $cvSnapshot, array $protectedBlock, array $requirements, string $language, ?string $notes, int $userId): array;
}
