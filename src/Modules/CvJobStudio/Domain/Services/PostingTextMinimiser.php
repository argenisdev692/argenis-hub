<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\Services;

/**
 * Incidental personal-data minimisation (T-149, NFR-8): before posting text
 * and snippets are stored, e-mail addresses and phone numbers survive ONLY
 * when they are the posting's stated application channel — otherwise they
 * become a placeholder. Named-contact lines are removed when detectable.
 * Reuses the LeadScout scrubber pattern, not its Art. 14 machinery.
 */
final readonly class PostingTextMinimiser
{
    #[\NoDiscard]
    public function minimise(string $text, ?string $applicationChannel): string
    {
        $minimised = $text;

        $minimised = (string) preg_replace(
            '/^[^\n]*(contact|contacto|contato|talent partner|recruiter|reclutador)[^\n]*$/mi',
            '[contact removed]',
            $minimised,
        );

        $channelEmail = $applicationChannel !== null && str_contains($applicationChannel, '@') ? $applicationChannel : null;
        $channelPhone = $applicationChannel !== null && preg_match('/\+?\d[\d\s\-().]{6,}\d/', $applicationChannel) === 1 ? $applicationChannel : null;

        $minimised = (string) preg_replace_callback(
            '/[\w.+-]+@[\w-]+\.[\w.]+/',
            static function (array $match) use ($channelEmail): string {
                $email = rtrim($match[0], '.');
                $trailingDot = strlen($match[0]) > strlen($email) ? '.' : '';

                if ($channelEmail !== null && str_contains($channelEmail, $email)) {
                    return $email.$trailingDot;
                }

                return '[email removed]';
            },
            $minimised,
        );

        $minimised = (string) preg_replace_callback(
            '/\+?\d[\d\s\-().]{6,}\d/',
            static fn (array $match): string => $channelPhone !== null && str_contains($channelPhone, trim($match[0])) ? $match[0] : '[phone removed]',
            $minimised,
        );

        return $minimised;
    }
}
