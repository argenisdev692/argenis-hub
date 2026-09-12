<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Application\DTOs;

use Modules\VideoEdits\Domain\Enums\SpeechCategory;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * V2 speech cleanup request block (US-10), shaped exactly like
 * {@see SilenceRemovalData} so the two switches read the same way.
 *
 * `enabled` is the single boolean that turns Whisper on. `categories` is
 * optional and defaults to all of them — the spec requires that a user CAN
 * choose categories, not that they must.
 */
#[MapInputName(SnakeCaseMapper::class)]
#[MapOutputName(SnakeCaseMapper::class)]
final class SpeechCleanupData extends Data
{
    /**
     * `$categories` arrives from JSON as strings; Spatie Data does not cast a
     * plain array into backed enums, so the type stays honest and
     * {@see effectiveCategories()} does the conversion.
     *
     * @param  list<SpeechCategory|string>  $categories
     */
    public function __construct(
        public bool $enabled,
        public array $categories = [],
        public ?string $language = null,
    ) {}

    /**
     * Empty means every category — that is what a user who switched cleanup on
     * without opening the options asked for.
     *
     * @return list<SpeechCategory>
     */
    #[\NoDiscard]
    public function effectiveCategories(): array
    {
        if ($this->categories === []) {
            return SpeechCategory::all();
        }

        return array_values(array_filter(array_map(
            static fn (SpeechCategory|string $category): ?SpeechCategory => $category instanceof SpeechCategory
                ? $category
                : SpeechCategory::tryFrom($category),
            $this->categories,
        )));
    }
}
