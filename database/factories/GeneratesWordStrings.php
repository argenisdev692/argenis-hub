<?php

declare(strict_types=1);

namespace Database\Factories;

/**
 * Builds a readable multi-word label from faker.
 *
 * `Faker\Provider\Lorem::words()` is declared `@return array|string`, because the
 * shape depends on the `$asText` flag — which PHPStan cannot narrow from a literal
 * argument. Every factory that needs "some words as one string" therefore routes
 * through here, so the union is resolved once, honestly, instead of being cast away
 * with `(string) words(3, true)` (which is a fatal error if the flag ever flips) or
 * per-call `implode()` on a possibly-string value.
 */
trait GeneratesWordStrings
{
    /**
     * N faker words joined by a single space.
     *
     * @param  int  $count  how many words to generate
     * @param  bool  $unique  draw from the shared unique pool, so repeated factory
     *                        runs in one process do not collide
     */
    protected function fakeWords(int $count, bool $unique = true): string
    {
        // Called with `$asText` left at its `false` default, so the array branch is
        // the real runtime shape; the string branch keeps the declared union honest.
        $words = $unique
            ? fake()->unique()->words($count)
            : fake()->words($count);

        return implode(' ', is_array($words) ? $words : [$words]);
    }
}
