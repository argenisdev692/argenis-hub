<?php

declare(strict_types=1);

use Modules\Invoices\Domain\Enums\InvoiceItemKind;
use Modules\Products\Domain\Enums\ProductType;

it('bills every product type as a catalog-backed line kind', function (ProductType $type, InvoiceItemKind $kind): void {
    expect(InvoiceItemKind::forProductType($type))->toBe($kind)
        ->and($kind->requiresProduct())->toBeTrue();
})->with([
    'course' => [ProductType::Course, InvoiceItemKind::Course],
    'video course' => [ProductType::VideoCourse, InvoiceItemKind::Video],
    'workshop' => [ProductType::Workshop, InvoiceItemKind::Course],
    'mentoring' => [ProductType::Mentoring, InvoiceItemKind::Course],
]);
