<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderProduct extends Model
{
    public $incrementing = false;
    protected $keyType = 'int';
    protected $guarded = [];
    protected $table = 'order_products';

    protected $casts = [
        'single_price'                => 'decimal:2',
        'shipping_cost'               => 'decimal:2',
        'subtotal'                    => 'decimal:2',
        'eta'                         => 'date',
        'variation_attributes'        => 'array',
        'has_fast_shipping'           => 'boolean',
        'selected_attribute_ids'      => 'array',
        'inventory_transferred_at'    => 'datetime',
    ];

    public function getVariationDisplayAttribute(): ?string
    {
        // PRIORITY 1: Use variation_display_name from database (new multi-attribute field)
        if (!empty($this->variation_display_name)) {
            return $this->variation_display_name;
        }

        // PRIORITY 2: Fallback to old logic for backward compatibility
        // Collect variation name and any attribute values, then de-duplicate case-insensitively
        $values = [];
        $name = trim((string)($this->variation_name ?? ''));
        if ($name !== '') { $values[] = $name; }

        if (is_array($this->variation_attributes) && !empty($this->variation_attributes)) {
            foreach ($this->variation_attributes as $v) {
                $val = is_array($v) ? ($v['value'] ?? ($v['name'] ?? null)) : $v;
                $val = trim((string)$val);
                if ($val !== '') { $values[] = $val; }
            }
        }

        // De-duplicate values (e.g., "Blue" and attribute value "Blue")
        $unique = [];
        $seen = [];
        foreach ($values as $v) {
            $key = mb_strtolower($v);
            if (!isset($seen[$key])) { $seen[$key] = true; $unique[] = $v; }
        }

        if (empty($unique)) return null;
        return implode(', ', $unique);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    /**
     * Get the status attribute (maps to item_status for backward compatibility)
     */
    public function getStatusAttribute(): ?string
    {
        return $this->item_status;
    }

    /**
     * Get the status color for display
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->item_status) {
            'pending' => 'warning',
            'processing' => 'info',
            'shipped' => 'primary',
            'delivered' => 'success',
            'cancelled', 'out_of_stock' => 'danger',
            'refunded' => 'secondary',
            default => 'secondary',
        };
    }

    /**
     * Get the status label for display
     */
    public function getStatusLabelAttribute(): string
    {
        return str_replace('_', ' ', ucwords($this->item_status ?? 'pending'));
    }
}
