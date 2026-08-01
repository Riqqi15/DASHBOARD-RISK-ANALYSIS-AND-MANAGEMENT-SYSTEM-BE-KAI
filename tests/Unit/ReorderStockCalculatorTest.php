<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ReorderStockCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ReorderStockCalculatorTest extends TestCase
{
    public function test_it_applies_the_validated_excel_reorder_formula(): void
    {
        $result = (new ReorderStockCalculator)->calculate(
            maxYearlyFailure: 10,
            averageYearlyFailure: 6,
            maxLeadTimeMonths: 4,
            averageLeadTimeMonths: 2,
        );

        $this->assertSame(28, $result['safety_stock']);
        $this->assertSame(12, $result['lead_time_demand']);
        $this->assertSame(40, $result['reorder_point']);
        $this->assertSame('calculated', $result['calculation_status']);
        $this->assertSame('kai-reorder-v1.0.0', $result['formula_version']);
    }

    public function test_it_clamps_negative_safety_stock_and_rounds_units_up(): void
    {
        $result = (new ReorderStockCalculator)->calculate(2, 4, 1, 2.25);

        $this->assertSame(0, $result['safety_stock']);
        $this->assertSame(9, $result['lead_time_demand']);
        $this->assertSame(9, $result['reorder_point']);
    }

    public function test_it_rejects_negative_inputs(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new ReorderStockCalculator)->calculate(-1, 1, 1, 1);
    }
}
