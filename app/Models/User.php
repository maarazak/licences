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
    public $timestamps = true;

    protected $fillable = [
        'id',
        'name',
        'email',
        'role',
        'first_name',
        'last_name',
        'phone',
        'country',
        'lang',
        'balance_xof',
        'password',
        'password_hash',
        'is_blocked',
        'mail_reminders',
        'totp_secret',
        'totp_confirmed_at',
        'totp_recovery_codes',
        'suspended_at',
        'last_login_at',
        'email_verified_at',
        'remember_token',
    ];

    protected $hidden = [
        'password',
        'password_hash',
        'remember_token',
        'totp_secret',
        'totp_recovery_codes',
    ];

    protected $casts = [
        'balance_xof' => 'integer',
        'is_blocked' => 'boolean',
        'mail_reminders' => 'boolean',
        'totp_confirmed_at' => 'datetime',
        'suspended_at' => 'datetime',
        'last_login_at' => 'datetime',
        'email_verified_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function getNameAttribute($value)
    {
        if ($value) {
            return $value;
        }
        $full = trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''));
        return $full ?: explode('@', $this->email ?? '')[0];
    }

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
}
