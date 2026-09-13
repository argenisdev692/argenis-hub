<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Application\DTOs;

use Illuminate\Validation\Validator;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * The course bible: the recurring fiction and voice every script and practice
 * pack must respect (US-3 · FR-9, D18).
 */
#[MapInputName(SnakeCaseMapper::class)]
#[MapOutputName(SnakeCaseMapper::class)]
final class CourseBibleData extends Data
{
    /**
     * @param  list<BibleOrganisationData>  $organisations
     * @param  list<BibleCharacterData>  $characters
     * @param  list<string>  $forbiddenPhrasings
     */
    public function __construct(
        #[DataCollectionOf(BibleOrganisationData::class), Max(30)]
        public array $organisations = [],
        #[DataCollectionOf(BibleCharacterData::class), Max(40)]
        public array $characters = [],
        #[Max(1000)]
        public string $audience = '',
        #[Max(500)]
        public string $tone = '',
        #[Max(160)]
        public ?string $taughtTool = null,
        #[Max(50)]
        public array $forbiddenPhrasings = [],
    ) {}

    /**
     * @return array<string, list<string>>
     */
    public static function rules(): array
    {
        // Empty lists are valid: the inferred `required` would reject them.
        return [
            'organisations' => ['sometimes', 'array', 'max:30'],
            'characters' => ['sometimes', 'array', 'max:40'],
            'forbidden_phrasings' => ['sometimes', 'array', 'max:50'],
            'forbidden_phrasings.*' => ['string', 'max:200'],
        ];
    }

    /**
     * Exactly one primary organisation when any are declared.
     */
    public static function withValidator(Validator $validator): void
    {
        $validator->after(static function (Validator $validator): void {
            $organisations = (array) ($validator->getData()['organisations'] ?? []);

            if ($organisations === []) {
                return;
            }

            $primaries = count(array_filter($organisations, static fn (mixed $organisation): bool => is_array($organisation) && filter_var($organisation['is_primary'] ?? false, FILTER_VALIDATE_BOOL)));

            if ($primaries !== 1) {
                $validator->errors()->add('organisations', 'Mark exactly one organisation as primary.');
            }
        });
    }

    public function primaryOrganisation(): ?BibleOrganisationData
    {
        return array_find($this->organisations, static fn (BibleOrganisationData $organisation): bool => $organisation->isPrimary);
    }

    /**
     * @return list<string>
     */
    public function organisationNames(): array
    {
        return array_values(array_map(static fn (BibleOrganisationData $organisation): string => $organisation->name, $this->organisations));
    }
}
