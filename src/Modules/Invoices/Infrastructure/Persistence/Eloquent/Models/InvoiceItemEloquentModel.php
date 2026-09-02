<?php

declare(strict_types=1);

namespace Modules\Invoices\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Invoices\Domain\Enums\InvoiceItemKind;
use Modules\Products\Infrastructure\Persistence\Eloquent\Models\ProductEloquentModel;
use Modules\Services\Infrastructure\Persistence\Eloquent\Models\ServiceEloquentModel;
use Shared\Domain\Enums\BillingUnit;

/**
 * @internal Persistence detail of {@see InvoiceEloquentModel}; not an Application dependency.
 *
 * @property int $id
 * @property int $invoice_id
 * @property int|null $service_id
 * @property int|null $product_id
 * @property InvoiceItemKind $kind
 * @property BillingUnit $unit
 * @property int $sort_order
 * @property string $title
 * @property string|null $description
 * @property string $quantity
 * @property string $unit_price
 * @property string $amount
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read InvoiceEloquentModel $invoice
 * @property-read ServiceEloquentModel|null $service
 * @property-read ProductEloquentModel|null $product
 *
 * @mixin \Eloquent
 */
#[Table('invoice_items')]
#[Fillable([
    'invoice_id',
    'service_id',
    'product_id',
    'kind',
    'unit',
    'sort_order',
    'title',
    'description',
    'quantity',
    'unit_price',
    'amount',
])]
final class InvoiceItemEloquentModel extends Model
{
    /**
     * @var list<string>
     */
    protected $hidden = ['id'];

    /**
     * @return BelongsTo<InvoiceEloquentModel, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(InvoiceEloquentModel::class, 'invoice_id');
    }

    /**
     * @return BelongsTo<ServiceEloquentModel, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(ServiceEloquentModel::class, 'service_id');
    }

    /**
     * @return BelongsTo<ProductEloquentModel, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(ProductEloquentModel::class, 'product_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'invoice_id' => 'integer',
            'service_id' => 'integer',
            'product_id' => 'integer',
            'kind' => InvoiceItemKind::class,
            'unit' => BillingUnit::class,
            'sort_order' => 'integer',
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }
}
