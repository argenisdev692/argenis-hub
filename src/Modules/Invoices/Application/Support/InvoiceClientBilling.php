<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\Support;

use Modules\Clients\Infrastructure\Persistence\Eloquent\Models\ClientEloquentModel;

/**
 * Client billing fields read live from {@see ClientEloquentModel} (via invoice relation).
 */
final class InvoiceClientBilling
{
    public static function resolveTaxId(?string $taxId, ?string $nif): ?string
    {
        $tax = trim((string) $taxId);
        if ($tax !== '' && $tax !== '0') {
            return $tax;
        }

        $nifValue = trim((string) $nif);
        if ($nifValue !== '' && $nifValue !== '0') {
            return $nifValue;
        }

        return null;
    }

    public static function taxIdForClient(?ClientEloquentModel $client): ?string
    {
        if ($client === null) {
            return null;
        }

        return self::resolveTaxId($client->tax_id, $client->nif);
    }
}
