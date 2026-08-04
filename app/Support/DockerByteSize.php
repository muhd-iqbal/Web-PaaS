<?php

namespace App\Support;

use InvalidArgumentException;

final class DockerByteSize
{
    public static function parse(string $value): int
    {
        $value = trim($value);

        if (! preg_match('/^(\d+(?:\.\d+)?)\s*([kmgtpe]?i?b)$/i', $value, $matches)) {
            throw new InvalidArgumentException("Invalid Docker byte size: {$value}");
        }

        $unit = strtolower($matches[2]);
        $powers = [
            'b' => 0, 'kb' => 1, 'kib' => 1, 'mb' => 2, 'mib' => 2,
            'gb' => 3, 'gib' => 3, 'tb' => 4, 'tib' => 4,
            'pb' => 5, 'pib' => 5, 'eb' => 6, 'eib' => 6,
        ];
        $base = str_contains($unit, 'i') ? 1024 : 1000;

        return (int) round((float) $matches[1] * ($base ** $powers[$unit]));
    }
}
