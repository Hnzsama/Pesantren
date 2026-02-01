<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['school_id', 'key', 'value'];

    public static function get(string $key, $default = null)
    {
        $schoolId = auth()->user()?->school_id;

        if (!$schoolId) {
            return $default;
        }

        return self::where('school_id', $schoolId)
            ->where('key', $key)
            ->first()?->value ?? $default;
    }

    public static function set(string $key, $value)
    {
        $schoolId = auth()->user()?->school_id;

        if (!$schoolId) {
            return null; // Or throw exception
        }

        return self::updateOrCreate(
            ['key' => $key, 'school_id' => $schoolId],
            ['value' => $value]
        );
    }

    public static function getGlobal(string $key, $default = null)
    {
        return self::whereNull('school_id')
            ->where('key', $key)
            ->first()?->value ?? $default;
    }

    public static function setGlobal(string $key, $value)
    {
        return self::updateOrCreate(
            ['key' => $key, 'school_id' => null],
            ['value' => $value]
        );
    }
}
