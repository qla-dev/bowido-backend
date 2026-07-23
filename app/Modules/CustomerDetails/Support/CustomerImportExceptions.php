<?php

namespace App\Modules\CustomerDetails\Support;

final class CustomerImportExceptions
{
    /** @var list<string> */
    public const SHARED_KVK_NUMBERS = [
        '24172907',
        '27251970',
    ];

    /** @var list<string> */
    public const SHARED_EMAIL_ADDRESSES = [
        'voorbereiding@dakkapellen-offerte.nl',
    ];

    public static function normalizeKvk(?string $value): string
    {
        return strtolower((string) preg_replace('/[\s.-]+/', '', trim((string) $value)));
    }

    public static function normalizeEmail(?string $value): string
    {
        return strtolower(trim((string) $value));
    }

    public static function allowsSharedKvk(?string $value): bool
    {
        return in_array(self::normalizeKvk($value), self::SHARED_KVK_NUMBERS, true);
    }

    public static function allowsSharedEmail(?string $value): bool
    {
        return in_array(self::normalizeEmail($value), self::SHARED_EMAIL_ADDRESSES, true);
    }
}
