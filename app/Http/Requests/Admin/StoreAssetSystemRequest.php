<?php

namespace App\Http\Requests\Admin;

use App\Models\AssetSystem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreAssetSystemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('create', AssetSystem::class);
    }

    public function rules(): array
    {
        return [
            'asset_group_id' => [
                'required',
                'integer',
                Rule::exists('asset_groups', 'id')->where(fn ($query) => $query->where('is_active', true)->whereNull('deleted_at')),
            ],
            'name' => ['required', 'string', 'max:255'],
            'normalized_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('asset_systems', 'normalized_name')->where(fn ($query) => $query->where('asset_group_id', $this->input('asset_group_id'))),
            ],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $name = $this->normalizedDisplayName();
        $this->merge([
            'name' => $name,
            'normalized_name' => mb_strtolower($name),
            'sort_order' => $this->input('sort_order') ?? 0,
        ]);
    }

    private function normalizedDisplayName(): string
    {
        $value = is_string($this->input('name')) ? $this->input('name') : '';
        $trimmed = preg_replace('/^\s+|\s+$/u', '', $value) ?? trim($value);

        return preg_replace('/\s+/u', ' ', $trimmed) ?? $trimmed;
    }
}
