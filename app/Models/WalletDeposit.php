<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WalletDeposit extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'wallet_deposits';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'reference',
        'user_id',
        'amount_xof',
        'customer_phone',
        'customer_country',
        'status',
        'payment_checkout_id',
        'payment_intent_id',
        'payment_url',
        'paid_at',
        'credited_at',
        'failure_code',
        'failure_message',
    ];

    protected $casts = [
        'amount_xof' => 'integer',
        'paid_at' => 'datetime',
        'credited_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
