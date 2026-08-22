<?php

namespace App\Http\Requests\Admin;

use App\Models\AssetGroup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreAssetGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('create', AssetGroup::class);
    }

    public function rules(): array
    {
        return [
            'unit_kerja_id' => [
                'required',
                'integer',
                Rule::exists('unit_kerjas', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'name' => ['required', 'string', 'max:255'],
            'normalized_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('asset_groups', 'normalized_name')->where(
                    fn ($query) => $query->where('unit_kerja_id', $this->input('unit_kerja_id')),
                ),
            ],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
            'dashboard_color' => ['nullable', 'regex:/^#[0-9A-F]{6}$/i'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $name = $this->normalizedDisplayName();
        $color = mb_strtoupper(trim((string) $this->input('dashboard_color')));
        $this->merge([
            'name' => $name,
            'normalized_name' => mb_strtolower($name),
            'sort_order' => $this->input('sort_order') ?? 0,
            'dashboard_color' => $color !== '' ? $color : null,
        ]);
    }

    private function normalizedDisplayName(): string
    {
        $value = is_string($this->input('name')) ? $this->input('name') : '';
        $trimmed = preg_replace('/^\s+|\s+$/u', '', $value) ?? trim($value);

        return preg_replace("/\s+/u", ' ', $trimmed) ?? $trimmed;
    }
}
