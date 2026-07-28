<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateAssetCategoryStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $category = $this->route('asset_group') ?? $this->route('asset_system') ?? $this->route('asset_subsystem');

        return $category !== null && Gate::forUser($this->user())->allows('status', $category);
    }

    public function rules(): array
    {
        return ['is_active' => ['required', 'boolean']];
    }
}
