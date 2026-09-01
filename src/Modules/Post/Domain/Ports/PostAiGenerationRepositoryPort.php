<?php

declare(strict_types=1);

namespace Modules\Post\Domain\Ports;

use Modules\Post\Infrastructure\Persistence\Eloquent\Models\PostAiGenerationEloquentModel;

interface PostAiGenerationRepositoryPort
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): PostAiGenerationEloquentModel;

    public function findByUuid(string $uuid): ?PostAiGenerationEloquentModel;

    /**
     * The polling lookup, scoped to the user who started the run.
     *
     * Separate from {@see self::findByUuid()} on purpose: the status endpoint
     * is reachable by anyone holding `CREATE_POSTS`, and a bare UUID lookup
     * there would let one author watch another author's in-flight draft
     * (OWASP A01). The job itself legitimately needs the unscoped lookup.
     */
    public function findByUuidForUser(string $uuid, int $userId): ?PostAiGenerationEloquentModel;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(PostAiGenerationEloquentModel $generation, array $attributes): PostAiGenerationEloquentModel;

    /**
     * Live phase transition — a hot path called several times per iteration,
     * so it writes the progress columns and nothing else.
     */
    public function markProgress(string $uuid, string $status, string $message, int $progress, int $iteration): void;
}
