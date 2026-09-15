<?php

declare(strict_types=1);

use Modules\Cvs\Domain\Enums\CvFileType;

it('resolves supported extensions to their file type', function (): void {
    expect(CvFileType::fromExtension('pdf'))->toBe(CvFileType::Pdf)
        ->and(CvFileType::fromExtension('md'))->toBe(CvFileType::Md)
        ->and(CvFileType::fromExtension('markdown'))->toBe(CvFileType::Md)
        ->and(CvFileType::fromExtension('PDF'))->toBe(CvFileType::Pdf);
});

it('rejects unsupported extensions', function (): void {
    expect(fn (): CvFileType => CvFileType::fromExtension('exe'))->toThrow(InvalidArgumentException::class);
});
