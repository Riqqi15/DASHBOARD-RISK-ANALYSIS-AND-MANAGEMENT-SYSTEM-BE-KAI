<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

final class ReorderStockCalculator
{
    public const FORMULA_VERSION = 'kai-reorder-v1.0.0';

    /**
     * @return array{
     *     safety_stock: int,
     *     lead_time_demand: int,
     *     reorder_point: int,
     *     calculation_status: string,
     *     formula_version: string
     * }
     */
    public function calculate(
        float $maxYearlyFailure,
        float $averageYearlyFailure,
        float $maxLeadTimeMonths,
        float $averageLeadTimeMonths,
    ): array {
        foreach ([$maxYearlyFailure, $averageYearlyFailure, $maxLeadTimeMonths, $averageLeadTimeMonths] as $value) {
            if ($value < 0) {
                throw new InvalidArgumentException('Input reorder stock tidak boleh negatif.');
            }
        }

        $rawSafetyStock = max(
            0.0,
            $maxYearlyFailure * $maxLeadTimeMonths - $averageYearlyFailure * $averageLeadTimeMonths,
        );
        $rawLeadTimeDemand = $averageYearlyFailure * $averageLeadTimeMonths;

        return [
            'safety_stock' => (int) ceil($rawSafetyStock),
            'lead_time_demand' => (int) ceil($rawLeadTimeDemand),
            'reorder_point' => (int) ceil($rawSafetyStock + $rawLeadTimeDemand),
            'calculation_status' => 'calculated',
            'formula_version' => self::FORMULA_VERSION,
        ];
    }
}
