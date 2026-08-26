<?php

declare(strict_types=1);

namespace Modules\Company\Application\DTOs;

use Modules\Company\Domain\Enums\SocialChannel;
use Modules\Company\Domain\ValueObjects\CompanySnapshot;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Every social channel, present or not.
 *
 * A fixed shape with explicit `null`s rather than the filtered map the older
 * `CompanyProfile::data()['socials']` returns: consumers get one generated
 * TypeScript type whose keys are always there, so a footer can render a channel
 * conditionally without narrowing an index signature first.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class CompanySocialsData extends Data
{
    public function __construct(
        public readonly ?string $facebook,
        public readonly ?string $github,
        public readonly ?string $instagram,
        public readonly ?string $linkedin,
        public readonly ?string $tiktok,
        public readonly ?string $twitter,
    ) {}

    public static function fromSnapshot(CompanySnapshot $company): self
    {
        return new self(
            facebook: $company->social(SocialChannel::Facebook)?->value,
            github: $company->social(SocialChannel::Github)?->value,
            instagram: $company->social(SocialChannel::Instagram)?->value,
            linkedin: $company->social(SocialChannel::Linkedin)?->value,
            tiktok: $company->social(SocialChannel::Tiktok)?->value,
            twitter: $company->social(SocialChannel::Twitter)?->value,
        );
    }
}
