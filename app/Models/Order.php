<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'orders';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'reference',
        'user_id',
        'product_id',
        'product_name',
        'supplier_product_id',
        'quantity',
        'unit_price_xof',
        'total_xof',
        'unit_cost_usd_cents',
        'usd_rate',
        'warranty',
        'expire_days',
        'customer_first_name',
        'customer_last_name',
        'customer_email',
        'customer_phone',
        'customer_country',
        'activation_emails',
        'custom_fields',
        'status',
        'payment_checkout_id',
        'payment_intent_id',
        'payment_url',
        'notify_token',
        'paid_at',
        'paid_with',
        'supplier_idem_key',
        'supplier_order_id',
        'supplier_reference',
        'supplier_status',
        'fulfil_attempts',
        'fulfil_locked_at',
        'delivery_encrypted',
        'delivered_at',
        'failure_code',
        'failure_message',
        'refund_requested_at',
        'refunded_at',
        'refund_method',
        'admin_note',
        'reminder_count',
        'last_reminder_at',
        'client_token',
    ];

    protected $hidden = [
        'notify_token',
        'delivery_encrypted',
        'unit_cost_usd_cents',
        'usd_rate',
        'supplier_product_id',
        'supplier_idem_key',
        'supplier_order_id',
        'supplier_reference',
        'supplier_status',
        'admin_note',
        'failure_code',
        'failure_message',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price_xof' => 'integer',
        'total_xof' => 'integer',
        'activation_emails' => 'array',
        'paid_at' => 'datetime',
        'delivered_at' => 'datetime',
        'refunded_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function events()
    {
        return $this->hasMany(OrderEvent::class, 'order_id')->orderBy('created_at', 'desc');
    }
}
