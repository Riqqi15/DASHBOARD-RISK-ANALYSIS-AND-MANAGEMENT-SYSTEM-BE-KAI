<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\RiskRegisterStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreRiskRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_active === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'unit_kerja_id' => [
                Rule::requiredIf(fn (): bool => $this->user()?->isPusat() === true),
                Rule::prohibitedIf(fn (): bool => $this->user()?->isUnit() === true),
                'nullable',
                'integer',
                Rule::exists('unit_kerjas', 'id')->where('is_active', true),
            ],
            'asset_id' => ['required', 'integer', 'exists:assets,id'],
            'part_number' => ['nullable', 'string', 'max:100'],
            'sub' => ['nullable', 'string', 'max:255'],
            'risk_event' => ['required', 'string', 'max:255'],
            'risk_cause' => ['required', 'string', 'max:5000'],
            'impact' => ['nullable', 'string', 'max:5000'],
            'part_name' => ['nullable', 'string', 'max:255'],
            'recommendation' => ['nullable', 'string', 'max:5000'],
            'likelihood' => ['required', 'integer', 'between:1,4'],
            'consequence' => ['required', 'integer', 'between:1,4'],
            'status' => ['required', Rule::enum(RiskRegisterStatus::class)],
        ];
    }
}
