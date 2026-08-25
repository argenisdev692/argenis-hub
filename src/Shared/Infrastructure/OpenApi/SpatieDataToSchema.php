<?php

declare(strict_types=1);

namespace Shared\Infrastructure\OpenApi;

use Dedoc\Scramble\Extensions\TypeToSchemaExtension;
use Dedoc\Scramble\Support\Generator\ClassBasedReference;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Types\ArrayType as OpenApiArray;
use Dedoc\Scramble\Support\Generator\Types\BooleanType as OpenApiBoolean;
use Dedoc\Scramble\Support\Generator\Types\IntegerType as OpenApiInteger;
use Dedoc\Scramble\Support\Generator\Types\NumberType as OpenApiNumber;
use Dedoc\Scramble\Support\Generator\Types\ObjectType as OpenApiObject;
use Dedoc\Scramble\Support\Generator\Types\StringType as OpenApiString;
use Dedoc\Scramble\Support\Generator\Types\Type as OpenApiType;
use Dedoc\Scramble\Support\Generator\Types\UnknownType as OpenApiUnknown;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use ReflectionClass;
use Shared\Providers\SharedServiceProvider;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\DataContainer;
use Spatie\LaravelData\Support\DataProperty;

/**
 * Documents Spatie `Data` DTOs in the OpenAPI schema.
 *
 * Scramble's own Laravel-Data support is a paid feature, and without it every
 * `Data` response is emitted as an EMPTY schema: consumers see that a `user`
 * key exists but learn nothing about its fields. Since this project uses Spatie
 * Data as the response shape everywhere (a `JsonResource` is a review failure),
 * that gap would apply to every endpoint the API ever grows.
 *
 * The schema is derived from Spatie's own reflection rather than from a
 * hand-written copy, which is the whole point: a duplicated schema silently
 * drifts the first time someone adds a property. `MapOutputName` is honored via
 * `outputMappedName`, so the documented keys are the snake_case ones actually
 * serialized.
 *
 * Registered in {@see SharedServiceProvider}. Scramble picks
 * the LAST extension whose `shouldHandle()` matches, and custom extensions are
 * merged after the built-ins, so this wins over `ArrayableToSchema` — which
 * would otherwise claim `Data` (it implements `Arrayable`) and produce nothing.
 */
final class SpatieDataToSchema extends TypeToSchemaExtension
{
    /**
     * Scramble instantiates the extension more than once during a build, so the
     * memo is static: describing the same DTO repeatedly is pure waste.
     *
     * @var array<class-string<Data>, iterable<DataProperty>>
     */
    private static array $dataClasses = [];

    public function shouldHandle(Type $type): bool
    {
        return $type instanceof ObjectType
            && $type->name !== ''
            && class_exists($type->name)
            && is_subclass_of($type->name, Data::class);
    }

    /**
     * @param  ObjectType  $type
     */
    public function toSchema(Type $type): OpenApiObject
    {
        $schema = new OpenApiObject;
        $required = [];

        foreach ($this->propertiesOf($type->name) as $property) {
            if ($property->hidden) {
                continue;
            }

            $name = $property->outputMappedName ?? $property->name;

            $schema->addProperty($name, $this->propertySchema($property));

            // `Optional` properties may be absent from the payload entirely,
            // which is a different contract from "present but null".
            if (! $property->type->isNullable && ! $property->type->isOptional) {
                $required[] = $name;
            }
        }

        return $schema->setRequired($required);
    }

    /**
     * Emits `$ref: #/components/schemas/{Name}` and registers the schema once,
     * so a DTO reused across endpoints is defined a single time.
     */
    public function reference(ObjectType $type): Reference
    {
        return ClassBasedReference::create('schemas', $type->name, $this->components);
    }

    /**
     * Builds the class description through Spatie's own factory, deliberately
     * WITHOUT going through the container-bound `DataConfig`.
     *
     * Resolving that singleton reads `data.structure_caching`, which talks to
     * the application cache store — so an unreachable Redis would take the
     * whole OpenAPI build down with it. Generating documentation is a build
     * step and must not depend on a live cache backend.
     *
     * @param  class-string<Data>  $dataClass
     * @return iterable<DataProperty>
     */
    private function propertiesOf(string $dataClass): iterable
    {
        return self::$dataClasses[$dataClass] ??= DataContainer::get()
            ->dataClassFactory()
            ->build(new ReflectionClass($dataClass))
            ->properties;
    }

    private function propertySchema(DataProperty $property): OpenApiType
    {
        $type = $property->type;

        // A nested DTO, or a collection of them, becomes a reference so the
        // nested shape is documented once and reused.
        if ($type->dataClass !== null) {
            $reference = ClassBasedReference::create('schemas', $type->dataClass, $this->components);

            return $type->kind->isDataCollectable()
                ? (new OpenApiArray)->setItems($reference)
                : $reference;
        }

        return $this->scalarSchema($property);
    }

    private function scalarSchema(DataProperty $property): OpenApiType
    {
        $accepted = array_keys($property->type->type->getAcceptedTypes());
        $nullable = $property->type->isNullable;

        $schema = match (true) {
            in_array('bool', $accepted, true) => new OpenApiBoolean,
            in_array('int', $accepted, true) => new OpenApiInteger,
            in_array('float', $accepted, true) => new OpenApiNumber,
            in_array('string', $accepted, true) => new OpenApiString,
            in_array('array', $accepted, true) => $this->arraySchema($property),
            default => new OpenApiUnknown,
        };

        return $schema->nullable($nullable);
    }

    /**
     * `list<string>` and friends carry their item type in `iterableItemType`;
     * without it the array is documented as untyped rather than guessed at.
     */
    private function arraySchema(DataProperty $property): OpenApiArray
    {
        $itemType = $property->type->iterableItemType;

        $items = match ($itemType) {
            'string' => new OpenApiString,
            'int' => new OpenApiInteger,
            'float' => new OpenApiNumber,
            'bool' => new OpenApiBoolean,
            null => null,
            default => class_exists($itemType) && is_subclass_of($itemType, Data::class)
                ? ClassBasedReference::create('schemas', $itemType, $this->components)
                : null,
        };

        $array = new OpenApiArray;

        return $items === null ? $array : $array->setItems($items);
    }
}
