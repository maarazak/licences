<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'products';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'supplier_id',
        'slug',
        'name',
        'display_name',
        'category',
        'shop_category',
        'duration_label',
        'image_url',
        'delivery_kind',
        'supplier_description',
        'cost_usd_cents',
        'list_usd_cents',
        'stock',
        'in_stock',
        'preorder',
        'expire_days',
        'warranty',
        'requires_email',
        'supplier_purchasable',
        'supplier_active',
        'unavailable_readings',
        'is_published',
        'is_featured',
        'featured_rank',
        'description',
        'description_en',
        'instructions',
        'instructions_en',
        'price_xof_override',
        'margin_percent_override',
        'synced_at',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'is_featured' => 'boolean',
        'in_stock' => 'boolean',
        'requires_email' => 'boolean',
        'supplier_purchasable' => 'boolean',
        'supplier_active' => 'boolean',
        'cost_usd_cents' => 'integer',
        'list_usd_cents' => 'integer',
        'price_xof_override' => 'integer',
        'margin_percent_override' => 'float',
        'stock' => 'integer',
        'expire_days' => 'integer',
        'featured_rank' => 'integer',
        'synced_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Prix de vente calculé en FCFA (formule exacte issue de pricing.ts).
     */
    public function getPriceXofAttribute(): int
    {
        if ($this->price_xof_override && $this->price_xof_override > 0) {
            return (int) $this->price_xof_override;
        }

        $usdRate = (int) Setting::get('usdRate', Setting::get('usd_rate', 669));
        $marginPercent = (int) ($this->margin_percent_override ?? Setting::get('marginPercent', Setting::get('default_margin_percent', 100)));
        $minMarginXof = (int) Setting::get('minMarginXof', 1000);
        $roundingStep = (int) Setting::get('roundingStep', 250);

        $costCents = (int) $this->cost_usd_cents;
        $costXof = (int) ceil(($costCents * $usdRate) / 100);

        $byPercent = ($costCents * $usdRate * (100 + $marginPercent)) / 10000;
        $byMinimum = ($this->margin_percent_override === null) ? ($costXof + max(0, $minMarginXof)) : 0;
        $raw = max($byPercent, $byMinimum);

        $step = max(1, $roundingStep);
        return max($step, (int) (ceil($raw / $step) * $step));
    }

    public function getCostXofAttribute(): int
    {
        $usdRate = (int) Setting::get('usdRate', Setting::get('usd_rate', 669));
        return (int) ceil(($this->cost_usd_cents * $usdRate) / 100);
    }

    public function getMarginXofAttribute(): int
    {
        return $this->price_xof - $this->cost_xof;
    }

    public function getIsProfitableAttribute(): bool
    {
        return $this->price_xof >= $this->cost_xof;
    }

    public function getTitleAttribute(): string
    {
        return $this->display_name ?: $this->name;
    }

    public function getShelfAttribute(): string
    {
        return $this->shop_category ?: $this->category ?: 'Sans catégorie';
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'product_id');
    }
}
