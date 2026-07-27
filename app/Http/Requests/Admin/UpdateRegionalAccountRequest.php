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
        $currentUnitId = $this->route('account')?->unit_kerja_id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->route('account'))],
            'unit_kerja_id' => [
                'required',
                Rule::exists('unit_kerjas', 'id')->where(fn ($query) => $query
                    ->whereNull('deleted_at')
                    ->where(fn ($units) => $units
                        ->where('is_active', true)
                        ->when($currentUnitId, fn ($current) => $current->orWhere('id', $currentUnitId)))),
            ],
        ];
    }
}
