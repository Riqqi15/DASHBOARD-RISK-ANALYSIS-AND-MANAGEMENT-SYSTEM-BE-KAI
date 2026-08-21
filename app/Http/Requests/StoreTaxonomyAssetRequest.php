<?php

namespace App\Http\Requests;

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\AssetCategoryNode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreTaxonomyAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('create', Asset::class);
    }

    public function rules(): array
    {
        return [
            'unit_kerja_id' => $this->user()->isPusat()
                ? ['required', Rule::exists('unit_kerjas', 'id')->where(fn ($query) => $query->where('is_active', true)->whereNull('deleted_at'))]
                : ['prohibited'],
            'asset_category_node_id' => [
                'required',
                Rule::exists('asset_category_nodes', 'id')->where(fn ($query) => $query->where('is_active', true)->whereNull('deleted_at')),
            ],
            'nama_aset' => ['required', 'string', 'max:255'],
            'jumlah_unit' => ['required', 'integer', 'min:0'],
            'tanggal_pemasangan' => ['nullable', 'date', 'before_or_equal:today'],
            'status' => ['required', Rule::enum(AssetStatus::class)],
        ];
    }

    public function unitId(): int
    {
        return $this->user()->isPusat()
            ? (int) $this->validated('unit_kerja_id')
            : (int) $this->user()->unit_kerja_id;
    }

    public function assetData(AssetCategoryNode $node, array $path): array
    {
        $names = collect($path)->keyBy(fn (AssetCategoryNode $item): int => $item->level->position);

        return [
            'unit_kerja_id' => $this->unitId(),
            'asset_category_node_id' => $node->id,
            'asset_subsystem_id' => $names->get(3)?->legacy_id,
            'nama_aset' => $this->validated('nama_aset'),
            'aset_prasarana_sintel' => $names->get(1)?->name ?? '',
            'system' => $names->get(2)?->name ?? '',
            'subsystem' => $names->get(3)?->name ?? '',
            'jumlah_unit' => $this->validated('jumlah_unit'),
            'tanggal_pemasangan' => $this->validated('tanggal_pemasangan'),
            'status' => $this->validated('status'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $values = [];
        foreach (['nama_aset'] as $field) {
            if (! $this->exists($field)) {
                continue;
            }
            $value = preg_replace('/\s+/u', ' ', trim($this->string($field)->toString())) ?? '';
            $values[$field] = $value;
        }
        $this->merge($values);
    }
}
