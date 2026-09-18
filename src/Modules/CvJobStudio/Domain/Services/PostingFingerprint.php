<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\Services;

/**
 * Cross-source identity (CHG-2, FR-10): the same job legitimately appears as
 * four different URLs. `sha256(normalized_employer | normalized_title |
 * normalized_location | posted_on_week)` collapses them into one posting with
 * many sources. Normalisation folds accents and case so "Möller GmbH" and
 * "Moller Gmbh" collide as intended.
 */
final readonly class PostingFingerprint
{
    #[\NoDiscard]
    public static function make(string $employer, string $title, ?string $location, ?string $postedAt): string
    {
        $week = $postedAt !== null ? date('o-W', strtotime($postedAt) ?: time()) : 'unknown';

        return hash('sha256', implode('|', [
            self::normalize($employer),
            self::normalize($title),
            self::normalize($location ?? ''),
            $week,
        ]));
    }

    /** @var array<string, string> */
    private const array ACCENT_MAP = [
        'ä' => 'a', 'ö' => 'o', 'ü' => 'u', 'ß' => 'ss',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a',
        'í' => 'i', 'ì' => 'i', 'î' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u',
        'ç' => 'c', 'ñ' => 'n',
    ];

    #[\NoDiscard]
    public static function normalize(string $value): string
    {
        $lowered = mb_strtolower(trim($value));

        if (class_exists(\Transliterator::class)) {
            $transliterator = \Transliterator::create('Any-Latin; Latin-ASCII');
            $ascii = $transliterator !== null ? $transliterator->transliterate($lowered) : false;

            if (is_string($ascii) && $ascii !== '') {
                return (string) preg_replace('/\s+/', ' ', $ascii);
            }
        }

        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $lowered);

        if (is_string($ascii) && trim($ascii) !== '') {
            return (string) preg_replace('/\s+/', ' ', $ascii);
        }

        return (string) preg_replace('/\s+/', ' ', strtr($lowered, self::ACCENT_MAP));
    }
}
