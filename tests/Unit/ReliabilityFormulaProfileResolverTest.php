<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ReliabilityFormulaProfileResolver;
use PHPUnit\Framework\TestCase;

final class ReliabilityFormulaProfileResolverTest extends TestCase
{
    public function test_it_preserves_interlocking_electric_profile_variations(): void
    {
        $profile = (new ReliabilityFormulaProfileResolver)->resolve([
            'downtime_mode' => 'minutes',
            'interval_baseline_date' => '2020-01-01',
            'failure_count_mode' => 'counta_all_minus_1',
            'spare_part_count_mode' => 'counta',
            'vandalism_count_mode' => 'counta',
            'failure_interval_row_count' => 4,
        ], '2017-01-01');

        self::assertSame('minutes', $profile['downtime_mode']);
        self::assertSame('2020-01-01', $profile['interval_baseline_date']);
        self::assertSame('counta_all_minus_1', $profile['failure_count_mode']);
        self::assertSame('counta', $profile['spare_part_count_mode']);
        self::assertSame(4, $profile['failure_interval_row_count']);
    }

    public function test_it_uses_auditable_defaults_for_missing_snapshot_profiles(): void
    {
        $profile = (new ReliabilityFormulaProfileResolver)->resolve([], '2017-01-01');

        self::assertSame('minutes', $profile['downtime_mode']);
        self::assertSame('counta', $profile['failure_count_mode']);
        self::assertSame('countif_ya', $profile['spare_part_count_mode']);
        self::assertTrue($profile['is_fallback']);
    }
}
