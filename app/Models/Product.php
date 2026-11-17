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
        'image_url',
        'delivery_kind',
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
     * Prix de vente calculé en FCFA.
     */
    public function getPriceXofAttribute(): int
    {
        if ($this->price_xof_override) {
            return $this->price_xof_override;
        }

        $usdRate = (float) Setting::get('usd_rate', 620);
        $margin = (float) ($this->margin_percent_override ?? Setting::get('default_margin_percent', 25));

        $costXof = ($this->cost_usd_cents / 100) * $usdRate;
        $price = $costXof * (1 + ($margin / 100));

        // Arrondi aux 50 FCFA supérieurs
        return max(500, (int) (ceil($price / 50) * 50));
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'product_id');
    }
}
