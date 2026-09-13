<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Tests\Support;

use Modules\CourseScripts\Domain\Ports\ResearchPort;
use Modules\CourseScripts\Domain\ValueObjects\ResearchBatch;
use Modules\CourseScripts\Domain\ValueObjects\ResearchFinding;

/**
 * Research without HTTP: one finding per query, a call counter, and switches
 * for an outage and for thin snippets.
 */
final class FakeResearch implements ResearchPort
{
    /** @var list<array{queries: list<string>, time_range: ?string}> */
    public array $searches = [];

    /** @var list<string> */
    public array $fetchedUrls = [];

    public bool $outage = false;

    public string $snippet = 'Contenido actual y detallado sobre el tema, con datos concretos de 2026 y ejemplos prácticos de uso que un guion puede aprovechar sin inventar nada. Incluye pasos, errores frecuentes y recomendaciones verificadas por fuentes recientes para profesionales.';

    public static function install(): self
    {
        $fake = new self;
        app()->instance(ResearchPort::class, $fake);

        return $fake;
    }

    public function search(array $queries, ?string $timeRange = null): ResearchBatch
    {
        $this->searches[] = ['queries' => $queries, 'time_range' => $timeRange];

        if ($this->outage || $queries === []) {
            return ResearchBatch::empty();
        }

        $findings = array_map(
            fn (string $query, int $index): ResearchFinding => new ResearchFinding(
                provider: 'tavily',
                query: $query,
                url: 'https://source.test/'.md5($query).'/'.$index,
                title: 'Fuente sobre '.$query,
                content: $this->snippet,
                score: 0.9,
            ),
            $queries,
            array_keys($queries),
        );

        return new ResearchBatch($findings, count($queries));
    }

    public function fetchFullPage(ResearchFinding $finding): ?ResearchFinding
    {
        $this->fetchedUrls[] = $finding->url;

        return $this->outage ? null : $finding->withContent('# Página completa de '.$finding->title."\n\n".str_repeat('Detalle ampliado. ', 40), true);
    }

    public function totalCalls(): int
    {
        return array_sum(array_map(static fn (array $search): int => count($search['queries']), $this->searches));
    }
}
