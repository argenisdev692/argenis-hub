<?php

declare(strict_types=1);

use Modules\CourseScripts\Domain\Exceptions\UnrecognisableIndexException;
use Modules\CourseScripts\Domain\Services\CourseIndexValidator;
use Modules\CourseScripts\Domain\ValueObjects\ParsedGroup;
use Modules\CourseScripts\Domain\ValueObjects\ParsedIndex;
use Modules\CourseScripts\Domain\ValueObjects\ParsedPoint;
use Modules\CourseScripts\Tests\Support\IndexFixtures;

/**
 * The line between "not a course index" (refuse, FR-7) and "an incomplete
 * course index" (accept and flag, FR-5).
 */
function point(int $position, int $group = 1): ParsedPoint
{
    return new ParsedPoint(
        position: $position,
        groupNumber: $group,
        title: 'Punto '.$position,
        topic: 'Tema',
        declaredDurationMinutes: 9,
        objective: 'Objetivo',
        learningAreas: ['a'],
        audienceObjectives: ['b'],
        mandatoryContent: ['c'],
        errorsToAvoid: ['d'],
        expectedResult: 'Resultado',
    );
}

/**
 * @param  list<ParsedPoint>  $points
 * @param  list<ParsedGroup>  $groups
 */
function index(array $points, ?array $groups = null): ParsedIndex
{
    return new ParsedIndex(
        title: 'Curso',
        language: 'es',
        declaredTotalMinutes: 9 * count($points),
        groups: $groups ?? [new ParsedGroup(1, 'Bloque único', 9 * count($points), 0)],
        points: $points,
    );
}

it('accepts the real reference index', function (): void {
    expect((new CourseIndexValidator)->reasons(IndexFixtures::parsedSample()))->toBeEmpty();
});

it('rejects an index with no points', function (): void {
    expect(fn () => (new CourseIndexValidator)->validate(index([])))
        ->toThrow(UnrecognisableIndexException::class);
});

it('accepts an index with no grouping at all', function (): void {
    // A bare table of contents has no blocks/modules/sections. Requiring one
    // is what rejected every generic index before this was fixed (FR-2a).
    $ungrouped = new ParsedPoint(position: 1, title: 'Un punto suelto');

    expect((new CourseIndexValidator)->reasons(index([$ungrouped], [])))->toBeEmpty();
});

it('rejects duplicate positions because ordering is the continuity contract', function (): void {
    $reasons = (new CourseIndexValidator)->reasons(index([point(1), point(2), point(2)]));

    expect($reasons)->toHaveCount(1)
        ->and($reasons[0])->toContain('Duplicate point positions: 2');
});

it('rejects points referencing a group that does not exist', function (): void {
    $reasons = (new CourseIndexValidator)->reasons(index([point(1, group: 1), point(2, group: 9)]));

    expect($reasons)->toHaveCount(1)
        ->and($reasons[0])->toContain('groups that do not exist: 9');
});

it('rejects an index larger than the configured ceiling', function (): void {
    $points = array_map(point(...), range(1, 6));

    $reasons = (new CourseIndexValidator(maxPoints: 5))->reasons(index($points));

    expect($reasons)->toHaveCount(1)
        ->and($reasons[0])->toContain('declares 6 points; the limit is 5');
});

it('reports numbering gaps without refusing the index', function (): void {
    // An index under construction legitimately holds 1-2 and 10; refusing it
    // would block the incremental authoring this module exists to support.
    $validator = new CourseIndexValidator;
    $parsed = index([point(1), point(2), point(10)]);

    expect($validator->reasons($parsed))->toBeEmpty()
        ->and($validator->numberingGaps($parsed))->toBe(range(3, 9));
});

it('accepts thin points, leaving them flagged instead of refused', function (): void {
    // The normal case for a generic index: a title and nothing else. Research
    // is what supplies the substance (FR-4a), so this must not be an error.
    $thin = new ParsedPoint(position: 1, title: 'Punto sin brief', groupNumber: 1);

    $parsed = index([$thin]);

    expect((new CourseIndexValidator)->reasons($parsed))->toBeEmpty()
        ->and($parsed->thinPoints())->toHaveCount(1)
        ->and($thin->hasFullBrief())->toBeFalse();
});
