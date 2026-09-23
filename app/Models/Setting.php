<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'description'
    ];

    public static function get($key, $default = null)
    {
        $setting = self::where('key', $key)->first();
        if (!$setting) {
            return $default;
        }

        switch ($setting->type) {
            case 'boolean':
                return $setting->value === '1' || $setting->value === 'true' || $setting->value === true;
            case 'integer':
                return (int) $setting->value;
            case 'json':
                return json_decode($setting->value, true);
            default:
                return $setting->value;
        }
    }

    public static function set($key, $value, $type = 'string', $group = 'general', $description = null)
    {
        switch ($type) {
            case 'boolean':
                $processedValue = $value ? '1' : '0';
                break;
            case 'integer':
                $processedValue = (string) $value;
                break;
            case 'json':
                $processedValue = is_array($value) ? json_encode($value) : $value;
                break;
            default:
                $processedValue = $value;
        }

        return self::updateOrCreate(
            ['key' => $key],
            [
                'value' => $processedValue,
                'type' => $type,
                'group' => $group,
                'description' => $description
            ]
        );
    }
}
