<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Asset;
use App\Models\RiskRegister;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class RiskRegisterService
{
    /** @param array<string, mixed> $data */
    public function create(array $data, User $actor, int $unitId): RiskRegister
    {
        return DB::transaction(function () use ($data, $actor, $unitId): RiskRegister {
            $asset = $this->authorizedAsset((int) $data['asset_id'], $actor, $unitId);

            return RiskRegister::query()->create([
                ...$data,
                'asset_id' => $asset->id,
            ]);
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function update(RiskRegister $register, array $data, User $actor, int $unitId): RiskRegister
    {
        return DB::transaction(function () use ($register, $data, $actor, $unitId): RiskRegister {
            $this->authorizeRegister($register, $actor, $unitId);
            $asset = $this->authorizedAsset((int) $data['asset_id'], $actor, $unitId);
            $register->fill([...$data, 'asset_id' => $asset->id])->save();

            return $register->refresh();
        }, 3);
    }

    public function delete(RiskRegister $register, User $actor, int $unitId): void
    {
        DB::transaction(function () use ($register, $actor, $unitId): void {
            $this->authorizeRegister($register, $actor, $unitId);
            $register->delete();
        }, 3);
    }

    private function authorizedAsset(int $assetId, User $actor, int $unitId): Asset
    {
        $authoritativeActor = User::query()
            ->whereKey($actor->id)
            ->where('is_active', true)
            ->first();
        if (! $authoritativeActor) {
            throw new AuthorizationException('Pengguna tidak aktif atau tidak ditemukan.');
        }

        $asset = Asset::query()->whereKey($assetId)->firstOrFail();
        if ($authoritativeActor->isUnit() && $unitId !== $authoritativeActor->unit_kerja_id) {
            throw new AuthorizationException('Unit kerja tidak sesuai dengan akun pengguna.');
        }
        if ($asset->unit_kerja_id !== $unitId) {
            throw new AuthorizationException('Aset berada di luar unit kerja pengguna.');
        }

        return $asset;
    }

    private function authorizeRegister(RiskRegister $register, User $actor, int $unitId): void
    {
        $register->loadMissing('asset:id,unit_kerja_id');
        if ($actor->isUnit() && $unitId !== $actor->unit_kerja_id) {
            throw new AuthorizationException('Unit kerja tidak sesuai dengan akun pengguna.');
        }
        if ($register->asset->unit_kerja_id !== $unitId) {
            throw new AuthorizationException('Risk Register berada di luar unit kerja pengguna.');
        }
    }
}
