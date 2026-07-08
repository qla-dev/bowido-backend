<?php

namespace App\Modules\Shared\Support;

use Illuminate\Support\Str;

class Normalizer
{
    public static function name(string $value): string
    {
        return Str::of($value)->squish()->title()->value();
    }

    public static function slug(string $value): string
    {
        return Str::of($value)->squish()->lower()->slug('_')->value();
    }

    public static function phoneNumber(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $trimmed = preg_replace('/(?!^\+)[^\d]/', '', trim($value)) ?? '';

        return $trimmed !== '' ? $trimmed : null;
    }

    public static function qrCode(string $value): string
    {
        return Str::of($value)
            ->upper()
            ->replaceMatches('/\s+/', '')
            ->value();
    }
}
