<?php

namespace App\Http\Requests\Admin;

use App\Enums\UnitType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUnitKerjaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isPusat() ?? false;
    }

    public function rules(): array
    {
        return [
            'code' => [
                'required',
                'string',
                'max:20',
                'alpha_dash:ascii',
                Rule::unique('unit_kerjas', 'code')->ignore($this->route('unit')),
            ],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(UnitType::class)],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['code' => mb_strtoupper($this->string('code')->toString())]);
    }
}
