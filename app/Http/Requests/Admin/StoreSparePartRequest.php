<?php

namespace App\Http\Requests\Admin;

use App\Models\AssetSubsystem;
use App\Models\SparePart;
use App\Services\PredictiveInventoryCalculator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSparePartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('create', SparePart::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'asset_subsystem_id' => [
                'required',
                'integer',
                Rule::exists('asset_subsystems', 'id')->where(
                    fn ($query) => $query->where('is_active', true)->whereNull('deleted_at'),
                ),
            ],
            'code' => ['required', 'string', 'max:50', Rule::unique('spare_parts', 'code')],
            'equipment' => ['required', 'string', 'max:255'],
            'detail_equipment' => ['required', 'string', 'max:255'],
            'max_yearly_failure' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'average_yearly_failure' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'max_lead_time_months' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'average_lead_time_months' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'safety_stock' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            'lead_time_demand' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            'reorder_point' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            'function_criterion' => ['nullable', 'integer', 'between:1,3'],
            'production_impact' => ['nullable', 'integer', 'between:0,3'],
            'severity' => ['required', 'string', Rule::in(['Desirable', 'Essential', 'Vital'])],
            'unit_of_measure' => ['required', 'string', 'max:30'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('asset_subsystem_id')) {
                    return;
                }

                $pathIsActive = AssetSubsystem::query()
                    ->whereKey($this->integer('asset_subsystem_id'))
                    ->where('is_active', true)
                    ->whereHas(
                        'assetSystem',
                        fn ($query) => $query
                            ->where('is_active', true)
                            ->whereHas('assetGroup', fn ($group) => $group->where('is_active', true)),
                    )
                    ->exists();

                if (! $pathIsActive) {
                    $validator
                        ->errors()
                        ->add(
                            'asset_subsystem_id',
                            'Subsistem aset atau kategori induknya tidak aktif atau tidak ditemukan.',
                        );
                }
            },
            function (Validator $validator): void {
                if ($validator->errors()->has('code')) {
                    return;
                }

                $code = $this->input('code');
                if (! is_string($code)) {
                    return;
                }

                $query = SparePart::withTrashed()->where('source_key', hash('sha256', 'manual|'.$code));
                $current = $this->route('spare_part');
                if ($current instanceof SparePart) {
                    $query->where('id', '!=', $current->id);
                }

                if ($query->exists()) {
                    $validator
                        ->errors()
                        ->add(
                            'code',
                            'Kode suku cadang pernah digunakan sebagai identitas sumber dan tidak dapat dipakai ulang.',
                        );
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'asset_subsystem_id.required' => 'Pilih subsistem aset.',
            'asset_subsystem_id.integer' => 'Subsistem aset yang dipilih tidak valid.',
            'asset_subsystem_id.exists' => 'Subsistem aset tidak aktif, terhapus, atau tidak ditemukan.',
            'code.required' => 'Kode suku cadang wajib diisi.',
            'code.string' => 'Kode suku cadang harus berupa teks.',
            'code.max' => 'Kode suku cadang maksimal 50 karakter.',
            'code.unique' => 'Kode suku cadang sudah digunakan.',
            'equipment.required' => 'Equipment wajib diisi.',
            'equipment.string' => 'Equipment harus berupa teks.',
            'equipment.max' => 'Equipment maksimal 255 karakter.',
            'detail_equipment.required' => 'Detail Equipment wajib diisi.',
            'detail_equipment.string' => 'Detail Equipment harus berupa teks.',
            'detail_equipment.max' => 'Detail Equipment maksimal 255 karakter.',
            'max_yearly_failure.required' => 'Maksimum kegagalan wajib diisi.',
            'average_yearly_failure.required' => 'Rata-rata kegagalan wajib diisi.',
            'max_lead_time_months.required' => 'Maksimum lead time wajib diisi.',
            'average_lead_time_months.required' => 'Rata-rata lead time wajib diisi.',
            'function_criterion.integer' => 'Criteria Function harus berupa angka.',
            'function_criterion.between' => 'Criteria Function harus bernilai 1 sampai 3.',
            'production_impact.integer' => 'Criteria Production Impact harus berupa angka.',
            'production_impact.between' => 'Criteria Production Impact harus bernilai 0 sampai 3.',
            'severity.required' => 'Criticality wajib dipilih.',
            'severity.string' => 'Criticality harus berupa teks.',
            'severity.in' => 'Criticality harus Desirable, Essential, atau Vital.',
            'unit_of_measure.required' => 'Satuan wajib diisi.',
            'unit_of_measure.string' => 'Satuan harus berupa teks.',
            'unit_of_measure.max' => 'Satuan maksimal 30 karakter.',
            '*.numeric' => 'Nilai harus berupa angka.',
            '*.decimal' => 'Nilai maksimal dua angka di belakang koma.',
            '*.integer' => 'Nilai harus berupa bilangan bulat.',
            '*.min' => 'Nilai tidak boleh kurang dari 0.',
            '*.max' => 'Nilai melebihi batas penyimpanan.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];
        foreach (['code', 'equipment', 'detail_equipment', 'severity', 'unit_of_measure'] as $field) {
            if (! $this->exists($field)) {
                continue;
            }

            $input = $this->input($field);
            if (! is_string($input)) {
                continue;
            }

            $value = preg_replace("/\s+/u", ' ', trim($input));
            $normalized[$field] = in_array($field, ['equipment', 'severity'], true) && $value === '' ? null : $value;
        }

        foreach (
            [
                'max_yearly_failure' => 'average_yearly_failure',
                'max_lead_time_months' => 'average_lead_time_months',
            ] as $maximumField => $averageField
        ) {
            $average = $this->averageFromMaximum($this->input($maximumField));
            if ($average !== null) {
                $normalized[$averageField] = $average;
            }
        }

        $criticality = $this->criticalityFromCriteria();
        if ($criticality !== null && blank($this->input('severity'))) {
            $normalized['severity'] = $criticality;
        }

        $this->merge($normalized);
    }

    private function averageFromMaximum(mixed $input): ?string
    {
        if (! is_string($input) && ! is_int($input) && ! is_float($input)) {
            return null;
        }

        $value = trim((string) $input);
        if (! preg_match('/^\d+(?:\.\d{1,2})?$/', $value)) {
            return null;
        }

        $number = (float) $value;
        if ($number < 0 || $number > 99999999.99) {
            return null;
        }

        return number_format($number / 2, 2, '.', '');
    }

    private function criticalityFromCriteria(): ?string
    {
        $function = $this->integerInput('function_criterion');
        $impact = $this->integerInput('production_impact');
        if ($function === null || $impact === null) {
            return null;
        }
        if ($function < 1 || $function > 3 || $impact < 0 || $impact > 3) {
            return null;
        }

        return app(PredictiveInventoryCalculator::class)->criticality($function, $impact);
    }

    private function integerInput(string $field): ?int
    {
        $input = $this->input($field);
        if (! is_string($input) && ! is_int($input)) {
            return null;
        }

        $value = trim((string) $input);
        if (! preg_match('/^-?\d+$/', $value)) {
            return null;
        }

        return (int) $value;
    }
}
