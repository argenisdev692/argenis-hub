<?php

declare(strict_types=1);

namespace Modules\Post\Application\Queries;

use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Post\Application\DTOs\PostAiGenerationData;
use Modules\Post\Domain\Ports\PostAiGenerationRepositoryPort;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The wizard's poll target. Side-effect free.
 *
 * Scoped to the caller: the route is open to anyone holding `CREATE_POSTS`, so
 * an unscoped UUID lookup would let one author watch another author's
 * in-flight draft (OWASP A01). A generation belonging to someone else is
 * reported as absent rather than forbidden — "exists but not yours" is itself
 * information the caller has no claim to.
 */
final readonly class GetPostAiGenerationHandler
{
    public function __construct(private PostAiGenerationRepositoryPort $generations) {}

    #[\NoDiscard]
    public function handle(string $uuid, Authenticatable $causer): PostAiGenerationData
    {
        $generation = $this->generations->findByUuidForUser($uuid, (int) $causer->getAuthIdentifier());

        if ($generation === null) {
            throw new NotFoundHttpException('Generation not found.');
        }

        return PostAiGenerationData::fromModel($generation);
    }
}
