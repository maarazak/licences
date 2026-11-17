<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    protected $table = 'settings';
    protected $primaryKey = 'key';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'key',
        'value',
    ];

    protected static ?array $runtimeCache = null;

    public static function get(string $key, $default = null)
    {
        if (static::$runtimeCache === null) {
            static::$runtimeCache = static::pluck('value', 'key')->all();
        }
        return static::$runtimeCache[$key] ?? $default;
    }

    public static function set(string $key, $value)
    {
        static::$runtimeCache = null;
        return static::updateOrCreate(['key' => $key], ['value' => $value]);
    }

    public static function clearRuntimeCache(): void
    {
        static::$runtimeCache = null;
    }
}
