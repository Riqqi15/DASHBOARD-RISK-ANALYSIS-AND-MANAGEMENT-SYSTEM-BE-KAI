<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

final class RiskAssessmentCalculator
{
    /** @var array<string, string> */
    private const LEVEL_BY_COORDINATE = [
        '1:1' => 'Low',
        '1:2' => 'Low',
        '1:3' => 'Medium',
        '1:4' => 'High',
        '2:1' => 'Low',
        '2:2' => 'Medium',
        '2:3' => 'High',
        '2:4' => 'Extreme',
        '3:1' => 'Medium',
        '3:2' => 'High',
        '3:3' => 'Extreme',
        '3:4' => 'Extreme',
        '4:1' => 'High',
        '4:2' => 'High',
        '4:3' => 'Extreme',
        '4:4' => 'Extreme',
    ];

    /** @return array{rating: int, level: string} */
    public function calculate(int $likelihood, int $consequence): array
    {
        $coordinate = $likelihood.':'.$consequence;

        if (! isset(self::LEVEL_BY_COORDINATE[$coordinate])) {
            throw new InvalidArgumentException('Likelihood dan consequence harus bernilai 1 sampai 4.');
        }

        return [
            'rating' => $likelihood * $consequence,
            'level' => self::LEVEL_BY_COORDINATE[$coordinate],
        ];
    }

    public function level(int $likelihood, int $consequence): string
    {
        return $this->calculate($likelihood, $consequence)['level'];
    }
}
