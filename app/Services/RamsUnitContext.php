<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\UnitKerja;
use Illuminate\Http\Request;

final class RamsUnitContext
{
    public const SESSION_KEY = 'rams.active_unit_id';

    public function resolve(Request $request): ?UnitKerja
    {
        $user = $request->user();
        if (! $user) {
            return null;
        }

        if ($user->isUnit()) {
            return UnitKerja::query()
                ->whereKey($user->unit_kerja_id)
                ->where('is_active', true)
                ->firstOrFail();
        }

        $unit = $this->requestedUnit($request);
        abort_if($request->query->has('area') && ! $unit, 404);
        $unit ??= UnitKerja::query()
            ->whereKey((int) $request->session()->get(self::SESSION_KEY))
            ->where('is_active', true)
            ->first();
        $unit ??= UnitKerja::query()->where('is_active', true)->orderBy('code')->first();

        if ($unit) {
            $request->session()->put(self::SESSION_KEY, $unit->id);
        } else {
            $request->session()->forget(self::SESSION_KEY);
        }

        return $unit;
    }

    private function requestedUnit(Request $request): ?UnitKerja
    {
        if ($request->query->has('unit_kerja_id')) {
            $unitId = filter_var($request->query('unit_kerja_id'), FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1],
            ]);

            return $unitId === false
                ? null
                : UnitKerja::query()->whereKey($unitId)->where('is_active', true)->first();
        }

        if (! $request->query->has('area')) {
            return null;
        }

        return UnitKerja::query()
            ->where('code', $this->databaseCode((string) $request->query('area')))
            ->where('is_active', true)
            ->first();
    }

    private function databaseCode(string $area): string
    {
        $normalized = strtoupper(str_replace(['-', ' '], '', trim($area)));
        if (preg_match('/^DAOP(\d+)$/', $normalized, $matches) === 1) {
            return 'DAOP-'.$matches[1];
        }

        $roman = ['1' => 'I', '2' => 'II', '3' => 'III', '4' => 'IV'];
        if (preg_match('/^DIVRE([1-4])$/', $normalized, $matches) === 1) {
            return 'DIVRE-'.$roman[$matches[1]];
        }
        if (preg_match('/^DIVRE(I|II|III|IV)$/', $normalized, $matches) === 1) {
            return 'DIVRE-'.$matches[1];
        }

        return strtoupper(trim($area));
    }
}
