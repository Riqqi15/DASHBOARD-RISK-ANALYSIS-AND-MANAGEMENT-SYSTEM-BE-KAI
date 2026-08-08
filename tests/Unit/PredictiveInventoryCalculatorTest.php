<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\PredictiveInventoryCalculator;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PredictiveInventoryCalculatorTest extends TestCase
{
    #[DataProvider('criticalityProvider')]
    public function test_criticality_matches_the_excel_condition_table(int $function, int $impact, string $expected): void
    {
        $this->assertSame($expected, (new PredictiveInventoryCalculator)->criticality($function, $impact));
    }

    /** @return array<string, array{int, int, string}> */
    public static function criticalityProvider(): array
    {
        return [
            '1-0' => [1, 0, 'Desirable'], '1-1' => [1, 1, 'Desirable'],
            '1-2' => [1, 2, 'Essential'], '1-3' => [1, 3, 'Vital'],
            '2-0' => [2, 0, 'Desirable'], '2-1' => [2, 1, 'Essential'],
            '2-2' => [2, 2, 'Essential'], '2-3' => [2, 3, 'Vital'],
            '3-0' => [3, 0, 'Essential'], '3-1' => [3, 1, 'Essential'],
            '3-2' => [3, 2, 'Vital'], '3-3' => [3, 3, 'Vital'],
        ];
    }

    public function test_it_calculates_inventory_policy_safety_stock_and_age_without_excel_gaps(): void
    {
        $result = (new PredictiveInventoryCalculator)->calculate([
            'function_criterion' => 3,
            'production_impact' => 2,
            'lead_time_months' => 5,
            'price_category' => 'High',
            'current_stock' => 0,
            'total_assets' => 100,
            'average_yearly_usage' => 12,
            'sla_percentage' => 1.5,
            'failure_safety_stock' => 3,
            'installed_at' => CarbonImmutable::parse('2000-01-01'),
            'lifetime_years' => 20,
        ], CarbonImmutable::parse('2026-08-01'));

        $this->assertSame('Vital', $result['criticality']);
        $this->assertSame('High', $result['lead_time_category']);
        $this->assertSame('More Pieces in Stock', $result['inventory_policy']);
        $this->assertSame(2, $result['needed_stock']);
        $this->assertSame(2, $result['proposal_quantity']);
        $this->assertSame('Sangat Wajar', $result['proposal_reasonableness']);
        $this->assertEqualsWithDelta(0.9, $result['safety_stock_usage'], 0.0001);
        $this->assertSame(2.0, $result['safety_stock_mca']);
        $this->assertSame(3.0, $result['safety_stock_failure']);
        $this->assertSame(3, $result['final_safety_stock']);
        $this->assertSame('Tua', $result['age_condition']);
        $this->assertSame('Melewati Umur Teknis', $result['lifetime_status']);
        $this->assertSame('calculated', $result['calculation_status']);
    }

    public function test_safety_stock_based_usage_matches_the_daop_1_workbook_formula(): void
    {
        $result = (new PredictiveInventoryCalculator)->calculate([
            'function_criterion' => 1,
            'production_impact' => 0,
            'lead_time_months' => 12,
            'price_category' => 'Low',
            'current_stock' => 0,
            'total_assets' => 10,
            'average_yearly_usage' => 20,
            'sla_percentage' => 1.5,
            'failure_safety_stock' => 0,
            'installed_at' => null,
            'lifetime_years' => null,
        ], CarbonImmutable::parse('2026-08-01'));

        $this->assertEqualsWithDelta(3.6, $result['safety_stock_usage'], 0.0001);
        $this->assertSame(1.0, $result['safety_stock_mca']);
        $this->assertSame(0.0, $result['safety_stock_failure']);
        $this->assertSame(4, $result['final_safety_stock']);
    }

    public function test_negative_current_stock_is_treated_as_a_deficit_when_proposing_stock(): void
    {
        $result = (new PredictiveInventoryCalculator)->calculate([
            'function_criterion' => 3,
            'production_impact' => 3,
            'lead_time_months' => 12,
            'price_category' => 'High',
            'current_stock' => -7,
            'total_assets' => 100,
            'average_yearly_usage' => 0,
            'sla_percentage' => 1.5,
            'failure_safety_stock' => 7,
            'installed_at' => null,
            'lifetime_years' => null,
        ], CarbonImmutable::parse('2026-08-01'));

        $this->assertSame(2, $result['needed_stock']);
        $this->assertSame(9, $result['proposal_quantity']);
    }

    #[DataProvider('leadTimeProvider')]
    public function test_lead_time_boundaries_are_complete(float $months, string $expected): void
    {
        $this->assertSame($expected, (new PredictiveInventoryCalculator)->leadTimeCategory($months));
    }

    /** @return array<string, array{float, string}> */
    public static function leadTimeProvider(): array
    {
        return [
            'below 1.5' => [1.49, 'Low'],
            'exactly 1.5' => [1.5, 'Medium'],
            'exactly 4' => [4.0, 'Medium'],
            'above 4' => [4.01, 'High'],
        ];
    }
}
