<?php

declare(strict_types=1);

namespace Modules\SocialMedia\Application\DTOs;

use Modules\SocialMedia\Infrastructure\Ai\SocialMediaAssetRenderer;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * What the writing agent decided a graphic should show — the cover's, or one
 * platform's. Colours are NOT part of it: the agent supplies a short title, a
 * one-sentence visual and a route, and the palette is applied downstream.
 *
 * It survives on the draft DTO (rather than being consumed immediately) so the
 * quality loop can pick a winning draft first and render artwork ONCE, from
 * this concept, in {@see SocialMediaAssetRenderer}.
 *
 * Routes: `a` title + emblem · `b` abstract, no typography · `c` roadmap whose
 * labels are drawn deterministically in PHP from {@see self::$svgSteps}.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class ImageConceptData extends Data
{
    /**
     * @param  list<string>  $svgSteps
     */
    public function __construct(
        public string $title,
        public string $visual,
        public string $route = 'a',
        public array $svgSteps = [],
    ) {}

    /**
     * Normalizes one raw `image_concept` object from the agent response. An
     * unknown route degrades to `a` rather than throwing — a graphic style is
     * not worth failing a whole generation over.
     *
     * @param  array<string, mixed>  $raw
     */
    #[\NoDiscard]
    public static function fromResponse(array $raw): self
    {
        $route = strtolower(trim((string) ($raw['route'] ?? 'a')));

        return new self(
            title: (string) ($raw['title'] ?? ''),
            visual: (string) ($raw['visual'] ?? ''),
            route: in_array($route, ['a', 'b', 'c'], true) ? $route : 'a',
            svgSteps: array_values(array_map(strval(...), (array) ($raw['svg_steps'] ?? []))),
        );
    }
}
