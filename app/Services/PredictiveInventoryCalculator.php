<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonInterface;
use InvalidArgumentException;

final class PredictiveInventoryCalculator
{
    public const FORMULA_VERSION = 'kai-predictive-v1.2.0';

    /** @var array<string, string> */
    private const CRITICALITY = [
        '1:0' => 'Desirable', '1:1' => 'Desirable', '1:2' => 'Essential', '1:3' => 'Vital',
        '2:0' => 'Desirable', '2:1' => 'Essential', '2:2' => 'Essential', '2:3' => 'Vital',
        '3:0' => 'Essential', '3:1' => 'Essential', '3:2' => 'Vital', '3:3' => 'Vital',
    ];

    /** @param array<string, mixed> $input
     * @return array<string, int|float|string|null>
     */
    public function calculate(array $input, CarbonInterface $asOf): array
    {
        $criticality = $this->criticality(
            (int) $input['function_criterion'],
            (int) $input['production_impact'],
        );
        $leadTimeMonths = (float) $input['lead_time_months'];
        $leadTimeCategory = $this->leadTimeCategory($leadTimeMonths);
        $priceCategory = $this->normalizedPriceCategory((string) $input['price_category']);
        $inventoryPolicy = $this->inventoryPolicy($criticality, $leadTimeCategory, $priceCategory);
        $neededStock = match ($inventoryPolicy) {
            'More Pieces in Stock' => 2,
            'Stock 1 Unit' => 1,
            default => 0,
        };
        $currentStock = (int) $input['current_stock'];
        $proposalQuantity = max(0, $neededStock - $currentStock);
        $totalAssets = max(0, (int) $input['total_assets']);
        $proposalReasonableness = $this->proposalReasonableness($proposalQuantity, $totalAssets);
        $averageYearlyUsage = max(0.0, (float) $input['average_yearly_usage']);
        $slaRate = max(0.0, (float) $input['sla_percentage']) / 100;
        $safetyStockUsage = $leadTimeMonths * $averageYearlyUsage * $slaRate;
        $safetyStockMca = (float) $neededStock;
        $safetyStockFailure = max(0.0, (float) $input['failure_safety_stock']);
        $finalSafetyStock = (int) ceil(max($safetyStockUsage, $safetyStockMca, $safetyStockFailure));
        $installedAt = $input['installed_at'] ?? null;
        $ageYears = $installedAt instanceof CarbonInterface
            ? max(0.0, $installedAt->diffInDays($asOf, false) / 365.25)
            : null;
        $lifetimeYears = isset($input['lifetime_years']) ? (float) $input['lifetime_years'] : null;

        return [
            'criticality' => $criticality,
            'lead_time_category' => $leadTimeCategory,
            'price_category' => $priceCategory,
            'inventory_policy' => $inventoryPolicy,
            'needed_stock' => $neededStock,
            'proposal_quantity' => $proposalQuantity,
            'proposal_reasonableness' => $proposalReasonableness,
            'safety_stock_usage' => $safetyStockUsage,
            'safety_stock_mca' => $safetyStockMca,
            'safety_stock_failure' => $safetyStockFailure,
            'final_safety_stock' => $finalSafetyStock,
            'age_years' => $ageYears,
            'age_condition' => $this->ageCondition($ageYears),
            'lifetime_status' => $ageYears !== null && $lifetimeYears !== null
                ? ($ageYears > $lifetimeYears ? 'Melewati Umur Teknis' : 'Dalam Umur Teknis')
                : null,
            'calculation_status' => $totalAssets > 0 ? 'calculated' : 'insufficient_data',
            'formula_version' => self::FORMULA_VERSION,
        ];
    }

    public function criticality(int $functionCriterion, int $productionImpact): string
    {
        $key = $functionCriterion.':'.$productionImpact;
        if (! isset(self::CRITICALITY[$key])) {
            throw new InvalidArgumentException('Kriteria fungsi harus 1–3 dan dampak produksi harus 0–3.');
        }

        return self::CRITICALITY[$key];
    }

    public function leadTimeCategory(float $months): string
    {
        if ($months < 0) {
            throw new InvalidArgumentException('Lead time tidak boleh negatif.');
        }

        return match (true) {
            $months < 1.5 => 'Low',
            $months <= 4 => 'Medium',
            default => 'High',
        };
    }

    public function inventoryPolicy(string $criticality, string $leadTimeCategory, string $priceCategory): string
    {
        $this->normalizedPriceCategory($priceCategory);

        return match ($criticality) {
            'Vital' => $leadTimeCategory === 'Low' ? 'Stock 1 Unit' : 'More Pieces in Stock',
            'Essential' => $leadTimeCategory === 'High' ? 'More Pieces in Stock' : 'Stock 1 Unit',
            'Desirable' => $leadTimeCategory === 'High' ? 'Stock 1 Unit' : 'No Stock',
            default => throw new InvalidArgumentException('Criticality tidak dikenali.'),
        };
    }

    private function normalizedPriceCategory(string $priceCategory): string
    {
        $normalized = ucfirst(mb_strtolower(trim($priceCategory)));
        if (! in_array($normalized, ['Low', 'Medium', 'High'], true)) {
            throw new InvalidArgumentException('Kategori harga harus Low, Medium, atau High.');
        }

        return $normalized;
    }

    private function proposalReasonableness(int $proposalQuantity, int $totalAssets): ?string
    {
        if ($proposalQuantity === 0) {
            return 'Tidak Ada Proposal';
        }
        if ($totalAssets <= 0) {
            return null;
        }

        $ratio = $proposalQuantity / $totalAssets;

        return match (true) {
            $ratio < 0.05 => 'Sangat Wajar',
            $ratio <= 0.10 => 'Wajar dengan Pengecualian',
            default => 'Tidak Wajar',
        };
    }

    private function ageCondition(?float $ageYears): ?string
    {
        if ($ageYears === null) {
            return null;
        }

        return match (true) {
            $ageYears <= 5 => 'Normal',
            $ageYears <= 20 => 'Menengah',
            default => 'Tua',
        };
    }
}
