<?php

namespace App\Http\Requests\Admin;

use App\Models\AssetCategoryNode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreAssetCategoryNodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('create', AssetCategoryNode::class);
    }

    public function rules(): array
    {
        return [
            'asset_category_level_id' => [
                'required',
                'integer',
                Rule::exists('asset_category_levels', 'id')->whereNull('deleted_at'),
            ],
            'unit_kerja_id' => [
                'nullable',
                'integer',
                Rule::exists('unit_kerjas', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'parent_id' => ['nullable', 'integer', Rule::exists('asset_category_nodes', 'id')->whereNull('deleted_at')],
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
            'dashboard_color' => ['nullable', 'regex:/^#[0-9A-F]{6}$/i'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $name = preg_replace("/\s+/u", ' ', trim($this->string('name')->toString())) ?? '';
        $color = mb_strtoupper(trim($this->string('dashboard_color')->toString()));
        $this->merge([
            'name' => $name,
            'sort_order' => $this->input('sort_order', 0),
            'dashboard_color' => $color !== '' ? $color : null,
        ]);
    }
}
