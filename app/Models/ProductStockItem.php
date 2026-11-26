<?php

namespace App\Models;

use App\Services\CryptoService;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** Un accès (compte, lien, code) du stock d'un produit manuel. Le contenu est chiffré au repos, jamais sérialisé. */
class ProductStockItem extends Model
{
    use HasUuids;

    protected $table = 'product_stock_items';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['id', 'product_id', 'content_encrypted', 'status', 'order_id', 'sold_at'];

    protected $hidden = ['content_encrypted'];

    protected $casts = ['sold_at' => 'datetime'];

    public static function aad(string $id): string
    {
        return "stock-item:{$id}";
    }

    /** Crée un article de stock en chiffrant son contenu (l'identifiant sert de contexte d'authentification). */
    public static function add(string $productId, string $plain): self
    {
        $id = Str::uuid()->toString();

        return static::create([
            'id' => $id,
            'product_id' => $productId,
            'content_encrypted' => CryptoService::encrypt($plain, self::aad($id)),
            'status' => 'available',
        ]);
    }

    public function plain(): ?string
    {
        return CryptoService::decrypt($this->content_encrypted, self::aad($this->id));
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
