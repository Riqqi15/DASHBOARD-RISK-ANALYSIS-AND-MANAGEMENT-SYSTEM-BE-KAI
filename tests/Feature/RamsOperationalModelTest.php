<?php

namespace Tests\Feature;

use App\Enums\RiskRegisterStatus;
use App\Models\Asset;
use App\Models\FailureLog;
use App\Models\ReliabilitySummary;
use App\Models\RiskMatrix;
use App\Models\RiskRegister;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RamsOperationalModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_operational_models_cast_values_and_expose_relations(): void
    {
        $asset = Asset::factory()->create();
        $matrix = RiskMatrix::factory()
            ->for($asset)
            ->create([
                'likelihood' => 4,
                'consequence' => 4,
            ]);
        $register = RiskRegister::factory()
            ->for($asset)
            ->create([
                'status' => RiskRegisterStatus::InProgress,
                'likelihood' => 2,
                'consequence' => 4,
            ]);
        $summary = ReliabilitySummary::factory()->for($asset)->create();
        $failure = FailureLog::factory()
            ->for($asset)
            ->create([
                'spare_part_replaced' => true,
                'vandalism' => false,
            ]);

        $this->assertTrue($matrix->asset->is($asset));
        $this->assertSame(16, $matrix->rating);
        $this->assertSame('Extreme', $matrix->level);
        $this->assertSame(RiskRegisterStatus::InProgress, $register->status);
        $this->assertSame(8, $register->rating);
        $this->assertSame('0.9973118280', $summary->availability);
        $this->assertTrue($failure->spare_part_replaced);
        $this->assertFalse($failure->vandalism);
        $this->assertTrue($asset->riskMatrix->is($matrix));
        $this->assertTrue($asset->riskRegisters->contains($register));
        $this->assertTrue($asset->reliabilitySummaries->contains($summary));
        $this->assertTrue($asset->failureLogs->contains($failure));
        $this->assertTrue($failure->creator->failureLogsCreated->contains($failure));
    }

    public function test_operational_scopes_limit_regional_users_to_their_unit(): void
    {
        $ownUser = User::factory()->unit()->create();
        $otherUnit = UnitKerja::factory()->create();
        $otherUser = User::factory()->unit($otherUnit)->create();
        $pusat = User::factory()->pusat()->create();
        $ownAsset = Asset::factory()->for($ownUser->unitKerja)->create();
        $otherAsset = Asset::factory()->for($otherUnit)->create();

        $ownMatrix = RiskMatrix::factory()->for($ownAsset)->create();
        $otherMatrix = RiskMatrix::factory()->for($otherAsset)->create();
        $ownRegister = RiskRegister::factory()->for($ownAsset)->create();
        $otherRegister = RiskRegister::factory()->for($otherAsset)->create();
        $ownSummary = ReliabilitySummary::factory()->for($ownAsset)->create();
        $otherSummary = ReliabilitySummary::factory()->for($otherAsset)->create();
        $ownFailure = FailureLog::factory()->for($ownAsset)->for($ownUser, 'creator')->create();
        $otherFailure = FailureLog::factory()->for($otherAsset)->for($otherUser, 'creator')->create();

        $this->assertSame([$ownMatrix->id], RiskMatrix::query()->visibleTo($ownUser)->pluck('id')->all());
        $this->assertSame([$ownRegister->id], RiskRegister::query()->visibleTo($ownUser)->pluck('id')->all());
        $this->assertSame([$ownSummary->id], ReliabilitySummary::query()->visibleTo($ownUser)->pluck('id')->all());
        $this->assertSame([$ownFailure->id], FailureLog::query()->visibleTo($ownUser)->pluck('id')->all());
        $this->assertEqualsCanonicalizing(
            [$ownMatrix->id, $otherMatrix->id],
            RiskMatrix::query()->visibleTo($pusat)->pluck('id')->all(),
        );
        $this->assertEqualsCanonicalizing(
            [$ownRegister->id, $otherRegister->id],
            RiskRegister::query()->visibleTo($pusat)->pluck('id')->all(),
        );
        $this->assertEqualsCanonicalizing(
            [$ownSummary->id, $otherSummary->id],
            ReliabilitySummary::query()->visibleTo($pusat)->pluck('id')->all(),
        );
        $this->assertEqualsCanonicalizing(
            [$ownFailure->id, $otherFailure->id],
            FailureLog::query()->visibleTo($pusat)->pluck('id')->all(),
        );
    }
}
