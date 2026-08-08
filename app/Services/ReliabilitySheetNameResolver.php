<?php

declare(strict_types=1);

namespace App\Services;

final class ReliabilitySheetNameResolver
{
    /** @var array<string, int> */
    private array $used = [];

    public function resolve(string $name): string
    {
        $base = trim((string) preg_replace('/[\\\\\/\?\*\[\]:]/u', ' ', $name));
        $base = preg_replace('/\s+/u', ' ', $base) ?: 'Subsystem';
        $base = mb_substr($base, 0, 31);
        $key = mb_strtolower($base);
        $sequence = ($this->used[$key] ?? 0) + 1;
        $this->used[$key] = $sequence;

        if ($sequence === 1) {
            return $base;
        }

        $suffix = " ({$sequence})";

        return mb_substr($base, 0, 31 - mb_strlen($suffix)).$suffix;
    }
}
