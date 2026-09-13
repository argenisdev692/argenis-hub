<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Infrastructure\Ai;

use Modules\CourseScripts\Application\DTOs\BibleCharacterData;
use Modules\CourseScripts\Application\DTOs\BibleOrganisationData;
use Modules\CourseScripts\Application\DTOs\CourseBibleData;
use Modules\CourseScripts\Domain\Exceptions\GenerationProviderException;
use Modules\CourseScripts\Domain\Ports\BibleProposerPort;
use Modules\CourseScripts\Domain\ValueObjects\BibleProposalContext;
use Psr\Log\LoggerInterface;
use Shared\Infrastructure\AI\AIClientInterface;
use Throwable;

/**
 * {@see BibleProposerPort} over the application's single LLM bridge. Model
 * output is untrusted: lengths are capped, empty rows dropped, exactly one
 * organisation kept primary.
 */
final readonly class LaravelAiBibleProposerAdapter implements BibleProposerPort
{
    public function __construct(
        private AIClientInterface $ai,
        private LoggerInterface $logger,
    ) {}

    public function propose(BibleProposalContext $context, string $provider): CourseBibleData
    {
        try {
            $response = $this->ai->generateStructured(ProposeCourseBibleAgent::class, $this->prompt($context), $provider);
        } catch (Throwable $exception) {
            $this->logger->error('course_scripts.bible_proposal_failed', ['exception' => $exception::class]);

            throw GenerationProviderException::providerFailed('bible');
        }

        $organisations = [];
        $hasPrimary = false;

        foreach ((array) ($response['organisations'] ?? []) as $row) {
            $name = is_array($row) ? trim((string) ($row['name'] ?? '')) : '';

            if ($name === '') {
                continue;
            }

            $isPrimary = (bool) ($row['is_primary'] ?? false) && ! $hasPrimary;
            $hasPrimary = $hasPrimary || $isPrimary;

            $organisations[] = new BibleOrganisationData(
                key: mb_substr(preg_replace('/[^a-z0-9_]+/', '_', strtolower(trim((string) ($row['key'] ?? $name)))) ?: 'organisation', 0, 60),
                name: mb_substr($name, 0, 160),
                role: mb_substr(trim((string) ($row['role'] ?? '')), 0, 200),
                sector: mb_substr(trim((string) ($row['sector'] ?? '')), 0, 120),
                isPrimary: $isPrimary,
            );
        }

        if ($organisations === []) {
            throw GenerationProviderException::invalidOutput('bible');
        }

        if (! $hasPrimary) {
            $organisations[0] = clone ($organisations[0], ['isPrimary' => true]);
        }

        $characters = [];

        foreach ((array) ($response['characters'] ?? []) as $row) {
            if (is_array($row) && trim((string) ($row['name'] ?? '')) !== '') {
                $characters[] = new BibleCharacterData(
                    name: mb_substr(trim((string) $row['name']), 0, 120),
                    role: mb_substr(trim((string) ($row['role'] ?? '')), 0, 200),
                    organisationKey: ($key = trim((string) ($row['organisation_key'] ?? ''))) === '' ? null : mb_substr($key, 0, 60),
                );
            }
        }

        $tool = trim((string) ($response['taught_tool'] ?? ''));

        return new CourseBibleData(
            organisations: $organisations,
            characters: array_slice($characters, 0, 40),
            audience: mb_substr(trim((string) ($response['audience'] ?? '')), 0, 1000),
            tone: mb_substr(trim((string) ($response['tone'] ?? '')), 0, 500),
            taughtTool: $tool === '' ? null : mb_substr($tool, 0, 160),
            forbiddenPhrasings: array_values(array_slice(array_filter(
                array_map(static fn (mixed $phrase): string => mb_substr(trim((string) $phrase), 0, 200), (array) ($response['forbidden_phrasings'] ?? [])),
                static fn (string $phrase): bool => $phrase !== '',
            ), 0, 50)),
        );
    }

    private function prompt(BibleProposalContext $context): string
    {
        return "COURSE LANGUAGE: {$context->language}\n\n".UntrustedContentBlock::wrapAll(array_filter([
            'course_title' => $context->courseTitle,
            'table_of_contents' => implode("\n", $context->tableOfContents),
            'author_course_notes' => $context->courseNotes,
            'sample_video_briefs' => $context->sampleBriefs,
            'style_exemplar' => $context->styleExemplar,
        ], static fn (?string $value): bool => $value !== null && trim($value) !== ''));
    }
}
