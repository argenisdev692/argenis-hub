<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Tests\Support;

use Modules\CourseScripts\Application\DTOs\BibleCharacterData;
use Modules\CourseScripts\Application\DTOs\BibleOrganisationData;
use Modules\CourseScripts\Application\DTOs\CourseBibleData;
use Modules\CourseScripts\Domain\Exceptions\GenerationProviderException;
use Modules\CourseScripts\Domain\Ports\BibleProposerPort;
use Modules\CourseScripts\Domain\ValueObjects\BibleProposalContext;

/**
 * Deterministic bible proposals, no HTTP, with a record of every context.
 */
final class FakeBibleProposer implements BibleProposerPort
{
    /** @var list<array{context: BibleProposalContext, provider: string}> */
    public array $calls = [];

    public bool $fail = false;

    public static function install(): self
    {
        $fake = new self;
        app()->instance(BibleProposerPort::class, $fake);

        return $fake;
    }

    public function propose(BibleProposalContext $context, string $provider): CourseBibleData
    {
        $this->calls[] = ['context' => $context, 'provider' => $provider];

        if ($this->fail) {
            throw GenerationProviderException::providerFailed('bible');
        }

        return new CourseBibleData(
            organisations: [
                new BibleOrganisationData('tecnoform', 'Tecnoform S.A.', 'Empresa del alumno', 'Industria', true),
                new BibleOrganisationData('almacenes_rivas', 'Almacenes Rivas', 'Cliente', 'Distribución'),
            ],
            characters: [new BibleCharacterData('Marta', 'Directora de operaciones', 'tecnoform')],
            audience: 'Profesionales no técnicos',
            tone: 'Cercano y práctico',
            taughtTool: 'Claude',
            forbiddenPhrasings: ['revolucionario'],
        );
    }
}
