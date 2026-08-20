<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateAssetCategoryNodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('update', $this->route('asset_category_node'));
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
            'dashboard_color' => ['nullable', 'regex:/^#[0-9A-F]{6}$/i'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $name = preg_replace('/\s+/u', ' ', trim($this->string('name')->toString())) ?? '';
        $color = mb_strtoupper(trim($this->string('dashboard_color')->toString()));
        $this->merge([
            'name' => $name,
            'sort_order' => $this->input('sort_order', 0),
            'dashboard_color' => $color !== '' ? $color : null,
            'is_active' => $this->boolean('is_active', true),
        ]);
    }
}
