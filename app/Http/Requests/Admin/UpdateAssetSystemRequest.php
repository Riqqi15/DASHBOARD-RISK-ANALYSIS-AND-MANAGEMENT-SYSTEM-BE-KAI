<?php

namespace App\Http\Requests\Admin;

use App\Models\AssetSystem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateAssetSystemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('update', $this->route('asset_system'));
    }

    public function rules(): array
    {
        /** @var AssetSystem $system */
        $system = $this->route('asset_system');

        return [
            'name' => ['required', 'string', 'max:255'],
            'normalized_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('asset_systems', 'normalized_name')
                    ->where(fn ($query) => $query->where('asset_group_id', $system->asset_group_id))
                    ->ignore($system),
            ],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
            'dashboard_color' => ['nullable', 'regex:/^#[0-9A-F]{6}$/i'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $name = $this->normalizedDisplayName();
        /** @var AssetSystem $system */
        $system = $this->route('asset_system');
        $this->merge([
            'name' => $name,
            'normalized_name' => mb_strtolower($name),
            'sort_order' => $this->input('sort_order') ?? $system->sort_order,
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
