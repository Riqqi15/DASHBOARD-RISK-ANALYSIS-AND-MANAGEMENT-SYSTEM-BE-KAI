<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\RamsUnitDetector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RamsUnitDetectorTest extends TestCase
{
    /** @return array<string, array{string, ?string}> */
    public static function filenames(): array
    {
        return [
            'daop 1' => ['Risk Analysis And Management System RAMS Daop 1.xlsm', 'DAOP-1'],
            'daop 4 compact' => ['RAMS DAOP4.xlsx', 'DAOP-4'],
            'daop 8' => ['dashboard-rams-daop 8.xlsm', 'DAOP-8'],
            'divre roman three' => ['RAMS Divre III.xlsm', 'DIVRE-III'],
            'divre roman four' => ['RAMS DIVRE IV.xlsx', 'DIVRE-IV'],
            'unknown' => ['RAMS Nasional.xlsx', null],
        ];
    }

    #[DataProvider('filenames')]
    public function test_detects_supported_unit_codes(string $filename, ?string $expected): void
    {
        $this->assertSame($expected, (new RamsUnitDetector)->detectCode($filename));
    }
}
