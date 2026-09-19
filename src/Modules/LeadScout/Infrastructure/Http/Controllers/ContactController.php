<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Modules\LeadScout\Application\Commands\ObjectContactHandler;
use Modules\LeadScout\Application\Commands\UpsertContactHandler;
use Modules\LeadScout\Application\DTOs\ContactData;
use Modules\LeadScout\Application\DTOs\UpsertContactData;

/**
 * Decisor endpoints (spec US-11, plan §5).
 */
final readonly class ContactController
{
    public function store(string $uuid, UpsertContactData $data, UpsertContactHandler $upsert): JsonResponse
    {
        $contact = $upsert->handleByCompany($uuid, $data);

        return response()->json(['data' => ContactData::fromEntity($contact)], 201);
    }

    public function update(UpsertContactData $data, string $uuid, UpsertContactHandler $upsert): JsonResponse
    {
        return response()->json(['data' => ContactData::fromEntity($upsert->handleUpdate($uuid, $data))]);
    }

    public function objection(string $uuid, ObjectContactHandler $object): Response
    {
        $object->handle($uuid);

        return response()->noContent();
    }
}
