<?php

declare(strict_types=1);

namespace App\Services;

final class RamsUnitDetector
{
    public function detectCode(string $filename): ?string
    {
        $normalized = mb_strtolower($filename);

        return match (true) {
            preg_match('/daop\s*1\b/u', $normalized) === 1 => 'DAOP-1',
            preg_match('/daop\s*4\b/u', $normalized) === 1 => 'DAOP-4',
            preg_match('/daop\s*8\b/u', $normalized) === 1 => 'DAOP-8',
            preg_match('/divre\s*iii\b/u', $normalized) === 1 => 'DIVRE-III',
            preg_match('/divre\s*iv\b/u', $normalized) === 1 => 'DIVRE-IV',
            default => null,
        };
    }
}
