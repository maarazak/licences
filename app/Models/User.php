<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasUuids, HasRoles;

    protected $table = 'users';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id',
        'email',
        'role',
        'first_name',
        'last_name',
        'phone',
        'country',
        'lang',
        'balance_xof',
        'totp_secret',
        'totp_confirmed_at',
        'totp_recovery_codes',
        'suspended_at',
    ];

    protected $casts = [
        'balance_xof' => 'integer',
        'totp_confirmed_at' => 'datetime',
        'suspended_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function orders()
    {
        return $this->hasMany(Order::class, 'user_id');
    }

    public function walletEntries()
    {
        return $this->hasMany(WalletEntry::class, 'user_id');
    }

    public function walletDeposits()
    {
        return $this->hasMany(WalletDeposit::class, 'user_id');
    }

    public function getRememberTokenName()
    {
        return '';
    }
}
