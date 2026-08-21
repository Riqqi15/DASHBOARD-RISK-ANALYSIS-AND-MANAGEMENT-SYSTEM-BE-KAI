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
            'operating_start_date' => ['nullable', 'date', 'before_or_equal:today'],
            'baseline_change_reason' => [
                Rule::excludeIf(fn (): bool => ! $this->baselineIsChanging()),
                Rule::requiredIf(fn (): bool => $this->baselineIsChanging()),
                'nullable',
                'string',
                'max:500',
            ],
            'baseline_change_confirmed' => [
                Rule::excludeIf(fn (): bool => ! $this->baselineIsChanging()),
                Rule::requiredIf(fn (): bool => $this->baselineIsChanging()),
                'accepted',
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function unitData(): array
    {
        return $this->safe()->only(['code', 'name', 'type', 'is_active', 'operating_start_date']);
    }

    public function baselineIsChanging(): bool
    {
        if (! $this->exists('operating_start_date')) {
            return false;
        }

        $current = $this->route('unit')?->operating_start_date?->toDateString();
        $requested = $this->filled('operating_start_date')
            ? trim($this->string('operating_start_date')->toString())
            : null;

        return $current !== $requested;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => mb_strtoupper($this->string('code')->toString()),
            'baseline_change_reason' => $this->filled('baseline_change_reason')
                ? preg_replace('/\s+/u', ' ', trim($this->string('baseline_change_reason')->toString()))
                : null,
        ]);
    }
}
