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

    /**
     * Réglage booléen tolérant : « 1 », « true », « on », « yes », « oui » sont vrais ; « 0 », « false », vide ou absent sont faux.
     * Accepte une clé de repli (le backoffice écrit à la fois la clé camelCase et la clé snake_case). Évite le piège PHP
     * (bool) 'false' === true.
     */
    public static function flag(string $key, ?string $fallbackKey = null, bool $default = false): bool
    {
        $value = static::get($key);
        if ($value === null && $fallbackKey !== null) {
            $value = static::get($fallbackKey);
        }
        if ($value === null) {
            return $default;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'on', 'yes', 'oui'], true);
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
