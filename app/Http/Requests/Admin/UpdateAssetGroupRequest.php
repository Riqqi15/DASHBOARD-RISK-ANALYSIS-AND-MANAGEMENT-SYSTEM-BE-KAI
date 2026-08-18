<?php

namespace App\Http\Requests\Admin;

use App\Models\AssetGroup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateAssetGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('update', $this->route('asset_group'));
    }

    public function rules(): array
    {
        /** @var AssetGroup $group */
        $group = $this->route('asset_group');

        return [
            'name' => ['required', 'string', 'max:255'],
            'normalized_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('asset_groups', 'normalized_name')
                    ->where(fn ($query) => $query->where('unit_kerja_id', $group->unit_kerja_id))
                    ->ignore($group),
            ],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
            'dashboard_color' => ['nullable', 'regex:/^#[0-9A-F]{6}$/i'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $name = $this->normalizedDisplayName();
        /** @var AssetGroup $group */
        $group = $this->route('asset_group');
        $this->merge([
            'name' => $name,
            'normalized_name' => mb_strtolower($name),
            'sort_order' => $this->input('sort_order') ?? $group->sort_order,
            'dashboard_color' => ($color = mb_strtoupper(trim((string) $this->input('dashboard_color')))) !== '' ? $color : null,
        ]);
    }

    private function normalizedDisplayName(): string
    {
        $value = is_string($this->input('name')) ? $this->input('name') : '';
        $trimmed = preg_replace('/^\s+|\s+$/u', '', $value) ?? trim($value);

        return preg_replace('/\s+/u', ' ', $trimmed) ?? $trimmed;
    }
}
