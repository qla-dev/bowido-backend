<?php

namespace App\Modules\Users\Support;

final class TemporaryPasswordGenerator
{
    public const LENGTH = 16;

    public function generate(): string
    {
        $groups = [
            'ABCDEFGHJKLMNPQRSTUVWXYZ',
            'abcdefghijkmnopqrstuvwxyz',
            '23456789',
            '!@#$%&*+-_?',
        ];

        $characters = array_map(
            fn (string $group): string => $group[random_int(0, strlen($group) - 1)],
            $groups,
        );
        $alphabet = implode('', $groups);

        while (count($characters) < self::LENGTH) {
            $characters[] = $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        // Fisher-Yates with random_int keeps the final character positions unpredictable.
        for ($index = count($characters) - 1; $index > 0; $index--) {
            $swapIndex = random_int(0, $index);
            [$characters[$index], $characters[$swapIndex]] = [$characters[$swapIndex], $characters[$index]];
        }

        return implode('', $characters);
    }
}
