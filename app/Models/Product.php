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
        'source',
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

    protected $hidden = [
        'cost_usd_cents',
        'list_usd_cents',
        'supplier_id',
        'supplier_purchasable',
        'supplier_active',
        'unavailable_readings',
        'margin_percent_override',
        'supplier_description',
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

    /**
     * Durée commerciale lisible (déduite des jours ou du titre, sans jamais afficher de garantie).
     */
    public function getDurationAttribute(): string
    {
        if (!empty($this->duration_label) && !preg_match('/warranty/i', $this->duration_label)) {
            return $this->duration_label;
        }

        $expireDays = (int) $this->expire_days;
        if ($expireDays >= 365) {
            $years = (int) round($expireDays / 365);
            return $years > 1 ? "{$years} ans" : '1 an';
        }
        if ($expireDays >= 30) {
            $months = (int) round($expireDays / 30);
            return $months > 1 ? "{$months} mois" : '1 mois';
        }
        if ($expireDays > 0) {
            return "{$expireDays} jours";
        }

        $title = $this->display_name ?: $this->name;
        if (preg_match('/(\d+)\s*(?:years?|ans?|yr)\b/i', $title, $m)) {
            return $m[1] . ' an' . ($m[1] > 1 ? 's' : '');
        }
        if (preg_match('/(\d+)\s*(?:months?|mois|m)\b/i', $title, $m) && !preg_match('/(?:token|credit|crédit)/i', $title)) {
            return $m[1] . ' mois';
        }
        if (preg_match('/(\d+)\s*(?:days?|jours?|d)\b/i', $title, $m)) {
            return $m[1] . ' jours';
        }
        if (preg_match('/(\d+)\s*(?:hours?|heures?|h)\b/i', $title, $m)) {
            return $m[1] . ' h';
        }

        return 'Selon offre';
    }

    /** Produit créé et géré à la main depuis le backoffice : la synchronisation du fournisseur ne le touche jamais. */
    public function isManual(): bool
    {
        return $this->source === 'manual';
    }

    public function stockItems()
    {
        return $this->hasMany(ProductStockItem::class);
    }

    /**
     * Recalcule le stock affiché d'un produit manuel d'après ses accès disponibles. Les colonnes existantes (stock, in_stock,
     * supplier_purchasable) sont réutilisées : accueil, boutique et commande fonctionnent sans changement.
     */
    public function refreshManualStock(): void
    {
        if (!$this->isManual()) {
            return;
        }

        $available = $this->stockItems()->where('status', 'available')->count();
        $this->forceFill([
            'stock' => $available,
            'in_stock' => $available > 0,
            'supplier_purchasable' => true,
            'supplier_active' => true,
            'preorder' => false,
        ])->save();
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'product_id');
    }
}
