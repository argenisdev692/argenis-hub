<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\ValueObjects;

/** A generated CV version: its lineage and content, never overwritten (FR-5). */
final readonly class CvVersionSnapshot
{
    /**
     * @param  array<array-key, mixed>  $content  ATS sections or a tailored summary/skills pair
     */
    public function __construct(
        public int $id,
        public string $uuid,
        public int $cvId,
        public ?int $structureId,
        public ?int $profileId,
        public ?int $postingId,
        public string $purpose,
        public string $language,
        public array $content,
    ) {}

    /**
     * Attributes for a child version derived from this one (translation).
     *
     * @param  array<array-key, mixed>  $content
     * @return array<string, mixed>
     */
    #[\NoDiscard]
    public function childAttributes(int $userId, string $language, array $content): array
    {
        return [
            'user_id' => $userId,
            'cv_id' => $this->cvId,
            'structure_id' => $this->structureId,
            'profile_id' => $this->profileId,
            'posting_id' => $this->postingId,
            'purpose' => $this->purpose,
            'language' => $language,
            'content' => $content,
            'parent_version_id' => $this->id,
            'rules_version' => 2,
        ];
    }
}
