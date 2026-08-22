<?php

namespace App\Http\Requests\Admin;

use App\Models\AssetCategoryLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreAssetCategoryLevelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('create', AssetCategoryLevel::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'normalized_name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('asset_category_levels', 'normalized_name')->withoutTrashed(),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'normalized_name.unique' => 'Nama level sudah digunakan oleh level aktif.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $name = preg_replace("/\s+/u", ' ', trim($this->string('name')->toString())) ?? '';
        $this->merge(['name' => $name, 'normalized_name' => mb_strtolower($name)]);
    }
}
