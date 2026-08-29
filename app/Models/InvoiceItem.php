<?php

namespace App\Models;

use App\Enums\InvoiceItemType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'invoice_id',
        'description',
        'item_type',
        'quantity',
        'unit_price',
        'discount_amount',
        'line_total',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
            'item_type' => InvoiceItemType::class,
        ];
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * (quantity x unit price) - discount, using string maths so the value is
     * exact rather than subject to float rounding.
     */
    public function computeLineTotal(): string
    {
        $gross = bcmul((string) $this->quantity, (string) $this->unit_price, 2);

        return bcsub($gross, (string) $this->discount_amount, 2);
    }
}
