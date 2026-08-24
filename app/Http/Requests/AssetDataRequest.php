<?php

namespace App\Http\Requests;

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\AssetSubsystem;
use App\Services\RamsUnitContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

abstract class AssetDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        $unitId = app(RamsUnitContext::class)->resolve($this)?->id;

        if ($this->route('asset')) {
            $asset = Asset::query()
                ->visibleTo($this->user())
                ->where('unit_kerja_id', $unitId ?? 0)
                ->find($this->route('asset'));

            abort_unless($asset, 404);

            return Gate::forUser($this->user())->allows('update', $asset);
        }

        return Gate::forUser($this->user())->allows('create', Asset::class);
    }

    public function rules(): array
    {
        $unitId = app(RamsUnitContext::class)->resolve($this)?->id;
        $currentSubsystemId = $this->route('asset')
            ? Asset::query()
                ->visibleTo($this->user())
                ->where('unit_kerja_id', $unitId ?? 0)
                ->whereKey($this->route('asset'))
                ->value('asset_subsystem_id')
            : null;

        return [
            'unit_kerja_id' => $this->user()->isPusat()
                ? [
                    'required',
                    Rule::in(array_filter([$unitId])),
                    Rule::exists('unit_kerjas', 'id')->where(
                        fn ($query) => $query->where('is_active', true)->whereNull('deleted_at'),
                    ),
                ]
                : ['prohibited'],
            'asset_subsystem_id' => [
                'required',
                'integer',
                Rule::exists('asset_subsystems', 'id')->whereNull('deleted_at'),
                function (string $attribute, mixed $value, \Closure $fail) use ($currentSubsystemId, $unitId): void {
                    if ((int) $value === (int) $currentSubsystemId && $currentSubsystemId !== null) {
                        return;
                    }

                    $isActive = AssetSubsystem::query()
                        ->whereKey($value)
                        ->where('is_active', true)
                        ->whereHas(
                            'assetSystem',
                            fn (Builder $system): Builder => $system
                                ->where('is_active', true)
                                ->whereHas(
                                    'assetGroup',
                                    fn (Builder $group): Builder => $group
                                        ->where('is_active', true)
                                        ->when(
                                            $unitId,
                                            fn (Builder $query): Builder => $query->where(
                                                fn (Builder $units): Builder => $units
                                                    ->whereNull('unit_kerja_id')
                                                    ->orWhere('unit_kerja_id', $unitId),
                                            ),
                                            fn (Builder $query): Builder => $query->whereRaw('1 = 0'),
                                        ),
                                ),
                        )
                        ->exists();

                    if (! $isActive) {
                        $fail('Kategori aset yang dipilih tidak aktif atau tidak tersedia.');
                    }
                },
            ],
            'nama_aset' => ['required', 'string', 'max:255'],
            'aset_prasarana_sintel' => ['prohibited'],
            'system' => ['prohibited'],
            'subsystem' => ['prohibited'],
            'jumlah_unit' => ['required', 'integer', 'min:0'],
            'tanggal_pemasangan' => ['nullable', 'date', 'before_or_equal:today'],
            'status' => ['required', Rule::enum(AssetStatus::class)],
        ];
    }

    public function assetData(AssetSubsystem $subsystem): array
    {
        $data = $this->safe()->only(['asset_subsystem_id', 'nama_aset', 'jumlah_unit', 'tanggal_pemasangan', 'status']);
        $data['unit_kerja_id'] = $this->user()->isPusat()
            ? $this->validated('unit_kerja_id')
            : $this->user()->unit_kerja_id;
        $data['asset_subsystem_id'] = $subsystem->id;
        $data['aset_prasarana_sintel'] = $subsystem->assetSystem->assetGroup->name;
        $data['system'] = $subsystem->assetSystem->name;
        $data['subsystem'] = $subsystem->name;

        return $data;
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (['nama_aset'] as $field) {
            if (! $this->exists($field)) {
                continue;
            }

            $value = preg_replace("/\s+/u", ' ', trim($this->string($field)->toString()));
            $normalized[$field] = $value;
        }

        $this->merge($normalized);
    }
}
