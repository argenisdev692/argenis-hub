<?php

declare(strict_types=1);

use Modules\Company\Domain\Enums\LogoVariant;
use Modules\Company\Domain\Enums\SocialChannel;
use Modules\Company\Domain\ValueObjects\CompanySnapshot;
use Modules\Company\Domain\ValueObjects\GeoCoordinates;
use Modules\Company\Domain\ValueObjects\PostalCode;
use Modules\Company\Domain\ValueObjects\WebUrl;

/**
 * The Company module's domain invariants, exercised without a database.
 *
 * These are the guards that matter most for values nobody looks at: the
 * coordinates are hidden inputs written by the address autocomplete, and the
 * social links are rendered straight into href attributes on public pages.
 */
function snapshot(array $overrides = []): CompanySnapshot
{
    return (new CompanySnapshot(
        uuid: '0198c7d0-0000-7000-8000-000000000000',
        companyName: 'Argenis Hub',
        legalName: null,
        description: null,
        website: null,
        email: null,
        phone: null,
        addressLine1: null,
        addressLine2: null,
        postalCode: null,
        city: null,
        state: null,
        country: null,
        countryCode: null,
        coordinates: null,
        logos: [],
        socials: [],
        nifNipc: null,
        nie: null,
        bankBeneficiary: null,
        bankIban: null,
        bankBic: null,
        bankName: null,
        invoiceNotes: null,
        createdAt: null,
        updatedAt: null,
    ))->with($overrides);
}

describe('GeoCoordinates', function (): void {
    it('accepts a point inside the valid ranges', function (): void {
        $point = new GeoCoordinates(40.2806, -7.5049);

        expect($point->latitude)->toBe(40.2806)
            ->and($point->longitude)->toBe(-7.5049);
    });

    it('rejects a latitude outside -90..90 — the classic swapped-pair bug', function (): void {
        new GeoCoordinates(140.2806, -7.5049);
    })->throws(InvalidArgumentException::class, 'Latitude');

    it('rejects a longitude outside -180..180', function (): void {
        new GeoCoordinates(10.0, 200.0);
    })->throws(InvalidArgumentException::class, 'Longitude');

    it('collapses a half-filled pair to null', function (): void {
        expect(GeoCoordinates::fromNullable(40.2806, null))->toBeNull()
            ->and(GeoCoordinates::fromNullable(null, -7.5049))->toBeNull()
            ->and(GeoCoordinates::fromNullable(null, null))->toBeNull();
    });
});

describe('PostalCode', function (): void {
    it('normalizes case and whitespace', function (string $input, string $expected): void {
        expect((new PostalCode($input))->value)->toBe($expected);
    })->with([
        ['  6200-386 ', '6200-386'],
        ['1011  ab', '1011 AB'],
        ['k1a0b1', 'K1A0B1'],
    ]);

    it('treats a blank value as absent rather than invalid', function (): void {
        expect(PostalCode::fromNullable(null))->toBeNull()
            ->and(PostalCode::fromNullable('   '))->toBeNull();
    });

    it('rejects a value longer than the column allows', function (): void {
        new PostalCode(str_repeat('9', 21));
    })->throws(InvalidArgumentException::class);
});

describe('WebUrl', function (): void {
    it('normalizes an absolute http(s) URL', function (): void {
        expect((new WebUrl(' https://WWW.LinkedIn.com/in/argenis/ '))->value)
            ->toBe('https://www.linkedin.com/in/argenis/');
    });

    it('refuses a scheme that would execute in the browser', function (string $hostile): void {
        WebUrl::fromNullable($hostile);
    })->with([
        'javascript:alert(1)',
        'data:text/html;base64,PHNjcmlwdD4=',
        'file:///etc/passwd',
    ])->throws(InvalidArgumentException::class);

    it('refuses a relative reference, which the browser would resolve against the visitor origin', function (): void {
        WebUrl::fromNullable('www.example.com');
    })->throws(InvalidArgumentException::class);

    it('treats a blank value as absent', function (): void {
        expect(WebUrl::fromNullable(null))->toBeNull()
            ->and(WebUrl::fromNullable(''))->toBeNull();
    });
});

describe('CompanySnapshot', function (): void {
    it('returns a new instance and leaves the original untouched', function (): void {
        $original = snapshot();
        $renamed = $original->with(['companyName' => 'Renamed']);

        expect($renamed->companyName)->toBe('Renamed')
            ->and($original->companyName)->toBe('Argenis Hub')
            ->and($renamed)->not->toBe($original);
    });

    it('merges logo keys instead of replacing the whole map', function (): void {
        $company = snapshot(['logos' => [
            LogoVariant::Logo->value => 'company/logos/logo/old.webp',
            LogoVariant::Mark->value => 'company/logos/mark/old.webp',
        ]]);

        $updated = $company->withLogos([LogoVariant::Logo->value => 'company/logos/logo/new.webp']);

        expect($updated->logo(LogoVariant::Logo))->toBe('company/logos/logo/new.webp')
            ->and($updated->logo(LogoVariant::Mark))->toBe('company/logos/mark/old.webp')
            ->and($updated->logo(LogoVariant::LogoWhite))->toBeNull();
    });

    it('reads a channel that was never filled in as null', function (): void {
        expect(snapshot()->social(SocialChannel::Tiktok))->toBeNull();
    });
});

describe('module enums', function (): void {
    it('maps every social channel onto a real company_data column', function (): void {
        $columns = array_map(
            static fn (SocialChannel $channel): string => $channel->column(),
            SocialChannel::cases(),
        );

        expect($columns)->toBe([
            'facebook_link', 'github_link', 'instagram_link',
            'linkedin_link', 'tiktok_link', 'twitter_link',
        ]);
    });

    it('maps every logo variant onto a real company_data column', function (): void {
        $columns = array_map(
            static fn (LogoVariant $variant): string => $variant->column(),
            LogoVariant::cases(),
        );

        expect($columns)->toBe(['logo_path', 'logo_white_path', 'mark_path']);
    });
});
