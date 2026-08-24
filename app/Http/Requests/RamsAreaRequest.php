<?php

namespace App\Http\Requests;

use App\Models\UnitKerja;
use App\Services\RamsUnitContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RamsAreaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_active === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'area' => [
                Rule::prohibitedIf(fn (): bool => $this->user()?->isUnit() === true),
                'nullable',
                'string',
                'max:20',
            ],
            'subsystem' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function selectedUnit(): ?UnitKerja
    {
        return app(RamsUnitContext::class)->resolve($this);
    }
}
