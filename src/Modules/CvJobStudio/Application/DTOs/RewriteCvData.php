<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\DTOs;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/** ATS rewrite input: output language of the new version. */
#[MapInputName(SnakeCaseMapper::class)]
final class RewriteCvData extends Data
{
    public function __construct(public readonly string $language = 'en') {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return ['language' => ['sometimes', 'string', 'in:es,en,pt-PT']];
    }
}
