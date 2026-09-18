<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\Ports;

interface CvJudgePort
{
    /**
     * @return array{verdict: string, verdict_reasons: list<string>, strengths: list<string>, improvements: list<string>, keyword_gaps: list<string>, xyz_gaps: list<string>, metric_questions: list<string>, provider: string, model: string}
     */
    public function judge(string $cvText, ?string $targetJobTitle): array;
}
