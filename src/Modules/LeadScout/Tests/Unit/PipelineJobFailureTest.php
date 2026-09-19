<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use Modules\LeadScout\Infrastructure\Queue\EnrichCompanyJob;

it('logs a final job failure without the exception message', function (): void {
    Log::spy();

    (new EnrichCompanyJob('0199a0a0-0000-7000-8000-000000000000'))
        ->failed(new RuntimeException('leaked page text jane@example.com'));

    Log::shouldHaveReceived('warning')->once()->withArgs(
        fn (string $message, array $context): bool => $message === 'lead-scout.job.failed'
            && $context === ['job' => 'EnrichCompanyJob', 'exception' => RuntimeException::class],
    );
});
