<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Ai;

use Modules\CvJobStudio\Domain\Enums\AiPurpose;
use Modules\CvJobStudio\Domain\Ports\CvStructureParserPort;

/** Structured-output CV parse (T-068). Raw text stays the source of truth. */
final readonly class LaravelAiCvStructureParser implements CvStructureParserPort
{
    public function __construct(private AiCallExecutor $calls) {}

    public function parse(string $rawText): array
    {
        $result = $this->calls->call(AiPurpose::CvStructureParse, CvStructureParserAgent::class, $rawText, 0);

        /** @var array<string, mixed> $data */
        $data = (array) $result['response'];

        return [...$data, 'provider' => $result['provider'], 'model' => $result['model'] ?? 'default', 'parser_version' => 'v2'];
    }
}
