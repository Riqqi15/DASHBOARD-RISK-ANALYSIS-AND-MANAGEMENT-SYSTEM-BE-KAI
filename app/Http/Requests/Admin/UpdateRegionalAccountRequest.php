<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRegionalAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isPusat() ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->route('account'))],
            'unit_kerja_id' => ['required', Rule::exists('unit_kerjas', 'id')->where(fn ($query) => $query->where('is_active', true)->whereNull('deleted_at'))],
        ];
    }
}
