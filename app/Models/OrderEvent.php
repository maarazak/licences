<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderEvent extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'order_events';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = true;

    protected static function booted()
    {
        static::creating(function ($event) {
            if (!$event->created_at) {
                $event->created_at = now();
            }
        });
    }

    protected $fillable = [
        'id',
        'order_id',
        'type',
        'event',
        'message',
        'actor',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
