<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Domain\Services;

/**
 * The course's registry of fictional organisations and characters (FR-9,
 * FR-13k · D18).
 *
 * A practice pack may introduce a supplier or a client; merging it here means
 * the next video reuses the same name instead of inventing another. Names are
 * compared case- and accent-insensitively, and exactly one organisation stays
 * primary.
 */
final readonly class BibleRegistry
{
    /**
     * @param  array<string, mixed>  $bible
     * @param  list<array{name: string, role?: string, sector?: string}>  $organisations
     * @param  list<array{name: string, role?: string, organisation?: string}>  $characters
     * @return array{bible: array<string, mixed>, changed: bool}
     */
    #[\NoDiscard]
    public function merge(array $bible, array $organisations, array $characters): array
    {
        $knownOrganisations = (array) ($bible['organisations'] ?? []);
        $knownCharacters = (array) ($bible['characters'] ?? []);
        $changed = false;

        $organisationKeys = [];

        foreach ($knownOrganisations as $organisation) {
            $organisationKeys[$this->normalise((string) ($organisation['name'] ?? ''))] = (string) ($organisation['key'] ?? '');
        }

        foreach ($organisations as $organisation) {
            $name = trim($organisation['name']);
            $normalised = $this->normalise($name);

            if ($normalised === '' || isset($organisationKeys[$normalised])) {
                continue;
            }

            $key = $this->uniqueKey($name, array_values($organisationKeys));
            $organisationKeys[$normalised] = $key;
            $knownOrganisations[] = [
                'key' => $key,
                'name' => mb_substr($name, 0, 160),
                'role' => mb_substr(trim($organisation['role'] ?? ''), 0, 200),
                'sector' => mb_substr(trim($organisation['sector'] ?? ''), 0, 120),
                'is_primary' => $knownOrganisations === [],
            ];
            $changed = true;
        }

        $characterNames = [];

        foreach ($knownCharacters as $character) {
            $characterNames[$this->normalise((string) ($character['name'] ?? ''))] = true;
        }

        foreach ($characters as $character) {
            $name = trim($character['name']);
            $normalised = $this->normalise($name);

            if ($normalised === '' || isset($characterNames[$normalised])) {
                continue;
            }

            $characterNames[$normalised] = true;
            $knownCharacters[] = [
                'name' => mb_substr($name, 0, 120),
                'role' => mb_substr(trim($character['role'] ?? ''), 0, 200),
                'organisation_key' => isset($character['organisation']) ? ($organisationKeys[$this->normalise($character['organisation'])] ?? null) : null,
            ];
            $changed = true;
        }

        $bible['organisations'] = $this->withSinglePrimary($knownOrganisations);
        $bible['characters'] = $knownCharacters;

        return ['bible' => $bible, 'changed' => $changed];
    }

    /**
     * @param  array<string, mixed>|null  $bible
     */
    public function isKnownOrganisation(?array $bible, string $name): bool
    {
        foreach ((array) ($bible['organisations'] ?? []) as $organisation) {
            if ($this->normalise((string) ($organisation['name'] ?? '')) === $this->normalise($name)) {
                return true;
            }
        }

        return false;
    }

    public function normalise(string $name): string
    {
        $ascii = TextNormaliser::ascii($name);

        // "S.L." and "SL" are the same company: drop dots and apostrophes
        // before collapsing the remaining punctuation into spaces.
        return strtolower($ascii)
            |> (static fn (string $value): string => str_replace(['.', "'"], '', $value))
            |> (static fn (string $value): string => preg_replace('/[^a-z0-9]+/', ' ', $value) ?? '')
            |> trim(...);
    }

    /**
     * @param  list<array<string, mixed>>  $organisations
     * @return list<array<string, mixed>>
     */
    private function withSinglePrimary(array $organisations): array
    {
        $primaryFound = false;

        foreach ($organisations as $index => $organisation) {
            $isPrimary = (bool) ($organisation['is_primary'] ?? false) && ! $primaryFound;
            $primaryFound = $primaryFound || $isPrimary;
            $organisations[$index]['is_primary'] = $isPrimary;
        }

        if (! $primaryFound && $organisations !== []) {
            $organisations[0]['is_primary'] = true;
        }

        return array_values($organisations);
    }

    /**
     * @param  list<string>  $taken
     */
    private function uniqueKey(string $name, array $taken): string
    {
        $base = str_replace(' ', '_', $this->normalise($name));
        $base = $base === '' ? 'organisation' : mb_substr($base, 0, 50);
        $key = $base;
        $suffix = 2;

        while (in_array($key, $taken, true)) {
            $key = $base.'_'.$suffix++;
        }

        return $key;
    }
}
