<?php

declare(strict_types=1);

namespace Modules\Company\Application\DTOs;

use Modules\Company\Domain\Enums\LogoVariant;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * The three brand marks as absolute URLs, ready to drop into an `<img src>`.
 *
 * Never carries the stored object key: the key is an internal storage detail and
 * leaking it would let a caller reason about the bucket layout (OWASP §12).
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class CompanyLogosData extends Data
{
    public function __construct(
        public readonly string $logo,
        public readonly string $logoWhite,
        public readonly string $mark,
    ) {}

    /**
     * @param  array<string, string>  $urls  {@see LogoVariant} value → absolute URL
     */
    public static function fromUrls(array $urls): self
    {
        return new self(
            logo: $urls[LogoVariant::Logo->value] ?? '',
            logoWhite: $urls[LogoVariant::LogoWhite->value] ?? '',
            mark: $urls[LogoVariant::Mark->value] ?? '',
        );
    }
}
