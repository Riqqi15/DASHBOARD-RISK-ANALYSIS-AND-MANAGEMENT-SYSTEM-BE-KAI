<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Asset;
use App\Models\UnitKerja;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

final class RamsWorkbookAssetResolver
{
    public function __construct(private readonly AssetCategoryResolver $categoryResolver) {}

    public function resolve(Worksheet $sheet, UnitKerja $unit, string $sheetName): ?Asset
    {
        $label = trim((string) ($sheet->getCell('B4')->getValue() ?? '')) ?: $sheetName;
        $normalizedCandidates = array_unique([
            $this->comparable($label),
            $this->comparable($sheetName),
        ]);
        $matches = Asset::query()
            ->where('unit_kerja_id', $unit->id)
            ->with('assetSubsystem')
            ->get()
            ->filter(function (Asset $asset) use ($normalizedCandidates): bool {
                $subsystemName = $asset->assetSubsystem?->name;

                return $subsystemName !== null
                    && in_array($this->comparable($subsystemName), $normalizedCandidates, true);
            });

        if ($matches->count() !== 1) {
            return null;
        }

        return $matches->first();
    }

    public function comparable(string $value): string
    {
        return str_replace(
            [
                'penunjuk',
                'kontak rel mekanik',
                'catu daya sintel',
                'track ciruit',
                'wesel terlayan setempat elektrik (s90)',
                'wesel terlayan setempat elektrik (bsg9)',
                'wesel terlayan setempat elektrik (bsg 9)',
                'wesel terlayan setempat elektrik s90',
                'wesel terlayan setempat elektrik bsg9',
                'wesel setempat elektrik s90',
                'wesel setempat elektrik bsg9',
            ],
            [
                'petunjuk',
                'kontak deteksi',
                'catu daya sinyal',
                'track circuit',
                'pengaman wesel setempat elektrik',
                'pengaman wesel setempat elektrik',
                'pengaman wesel setempat elektrik',
                'pengaman wesel setempat elektrik',
                'pengaman wesel setempat elektrik',
                'pengaman wesel setempat elektrik',
                'pengaman wesel setempat elektrik',
            ],
            $this->categoryResolver->normalize($value),
        );
    }
}
