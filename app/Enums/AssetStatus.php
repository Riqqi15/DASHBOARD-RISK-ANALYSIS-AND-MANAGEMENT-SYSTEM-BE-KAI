<?php

namespace App\Enums;

enum AssetStatus: string
{
    case Aktif = 'aktif';
    case Nonaktif = 'nonaktif';
    case DalamPerbaikan = 'dalam_perbaikan';

    public function label(): string
    {
        return match ($this) {
            self::Aktif => 'Aktif',
            self::Nonaktif => 'Nonaktif',
            self::DalamPerbaikan => 'Dalam perbaikan',
        };
    }
}
