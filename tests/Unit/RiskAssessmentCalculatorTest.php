<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\RiskAssessmentCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RiskAssessmentCalculatorTest extends TestCase
{
    #[DataProvider('excelMatrixProvider')]
    public function test_it_matches_the_excel_four_by_four_matrix(
        int $likelihood,
        int $consequence,
        string $expectedLevel,
    ): void {
        $result = (new RiskAssessmentCalculator)->calculate($likelihood, $consequence);

        $this->assertSame($likelihood * $consequence, $result['rating']);
        $this->assertSame($expectedLevel, $result['level']);
    }

    /** @return array<string, array{int, int, string}> */
    public static function excelMatrixProvider(): array
    {
        return [
            'L1 C1' => [1, 1, 'Low'],
            'L1 C2' => [1, 2, 'Low'],
            'L1 C3' => [1, 3, 'Medium'],
            'L1 C4' => [1, 4, 'High'],
            'L2 C1' => [2, 1, 'Low'],
            'L2 C2' => [2, 2, 'Medium'],
            'L2 C3' => [2, 3, 'High'],
            'L2 C4' => [2, 4, 'Extreme'],
            'L3 C1' => [3, 1, 'Medium'],
            'L3 C2' => [3, 2, 'High'],
            'L3 C3' => [3, 3, 'Extreme'],
            'L3 C4' => [3, 4, 'Extreme'],
            'L4 C1' => [4, 1, 'High'],
            'L4 C2' => [4, 2, 'High'],
            'L4 C3' => [4, 3, 'Extreme'],
            'L4 C4' => [4, 4, 'Extreme'],
        ];
    }

    #[DataProvider('invalidCoordinateProvider')]
    public function test_it_rejects_coordinates_outside_the_excel_scale(int $likelihood, int $consequence): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new RiskAssessmentCalculator)->calculate($likelihood, $consequence);
    }

    /** @return array<string, array{int, int}> */
    public static function invalidCoordinateProvider(): array
    {
        return [
            'zero likelihood' => [0, 1],
            'fifth likelihood' => [5, 1],
            'zero consequence' => [1, 0],
            'fifth consequence' => [1, 5],
        ];
    }
}
