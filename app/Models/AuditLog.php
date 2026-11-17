<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'audit_logs';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = true;

    protected $fillable = [
        'id',
        'admin_id',
        'admin_email',
        'action',
        'target',
        'details',
        'ip',
        'created_at',
        'updated_at',
    ];

    public static function record(string $action, string $target = '', string|array $details = ''): self
    {
        $user = auth()->user();
        return self::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'admin_id' => $user?->id ?? '00000000-0000-0000-0000-000000000000',
            'admin_email' => $user?->email ?? 'system',
            'action' => $action,
            'target' => $target,
            'details' => is_array($details) ? json_encode($details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : (string) $details,
            'ip' => request()->ip(),
            'created_at' => now(),
        ]);
    }
}
