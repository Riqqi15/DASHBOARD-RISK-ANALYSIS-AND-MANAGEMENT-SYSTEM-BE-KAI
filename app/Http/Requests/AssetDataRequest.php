<?php

namespace App\Http\Requests;

use App\Enums\AssetStatus;
use App\Models\Asset;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

abstract class AssetDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->route('asset')) {
            $asset = Asset::query()
                ->visibleTo($this->user())
                ->find($this->route('asset'));

            abort_unless($asset, 404);

            return Gate::forUser($this->user())->allows('update', $asset);
        }

        return Gate::forUser($this->user())->allows('create', Asset::class);
    }

    public function rules(): array
    {
        return [
            'unit_kerja_id' => $this->user()->isPusat()
                ? ['required', Rule::exists('unit_kerjas', 'id')->where(fn ($query) => $query->where('is_active', true)->whereNull('deleted_at'))]
                : ['prohibited'],
            'nama_aset' => ['required', 'string', 'max:255'],
            'aset_prasarana_sintel' => ['required', 'string', 'max:255'],
            'system' => ['required', 'string', 'max:255'],
            'subsystem' => ['required', 'string', 'max:255'],
            'lokasi' => ['nullable', 'string', 'max:255'],
            'jumlah_unit' => ['required', 'integer', 'min:0'],
            'tanggal_pemasangan' => ['nullable', 'date', 'before_or_equal:today'],
            'status' => ['required', Rule::enum(AssetStatus::class)],
        ];
    }

    public function assetData(): array
    {
        $data = $this->validated();
        $data['unit_kerja_id'] = $this->user()->isPusat()
            ? $data['unit_kerja_id']
            : $this->user()->unit_kerja_id;

        return $data;
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (['nama_aset', 'aset_prasarana_sintel', 'system', 'subsystem', 'lokasi'] as $field) {
            if (! $this->exists($field)) {
                continue;
            }

            $value = preg_replace('/\s+/u', ' ', trim($this->string($field)->toString()));
            $normalized[$field] = $field === 'lokasi' && $value === '' ? null : $value;
        }

        $this->merge($normalized);
    }
}
