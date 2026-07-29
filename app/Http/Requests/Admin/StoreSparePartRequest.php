<?php

namespace App\Http\Requests\Admin;

use App\Models\AssetSubsystem;
use App\Models\SparePart;
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
                Rule::exists('asset_subsystems', 'id')->where(fn ($query) => $query
                    ->where('is_active', true)
                    ->whereNull('deleted_at')),
            ],
            'code' => ['required', 'string', 'max:50', Rule::unique('spare_parts', 'code')],
            'equipment' => ['nullable', 'string', 'max:255'],
            'detail_equipment' => ['required', 'string', 'max:255'],
            'max_yearly_failure' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'average_yearly_failure' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'max_lead_time_months' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'average_lead_time_months' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'safety_stock' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            'lead_time_demand' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            'reorder_point' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            'severity' => ['nullable', 'string', 'max:100'],
            'unit_of_measure' => ['required', 'string', 'max:30'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('asset_subsystem_id')) {
                return;
            }

            $pathIsActive = AssetSubsystem::query()
                ->whereKey($this->integer('asset_subsystem_id'))
                ->where('is_active', true)
                ->whereHas('assetSystem', fn ($query) => $query
                    ->where('is_active', true)
                    ->whereHas('assetGroup', fn ($group) => $group->where('is_active', true)))
                ->exists();

            if (! $pathIsActive) {
                $validator->errors()->add('asset_subsystem_id', 'Subsistem aset atau kategori induknya tidak aktif atau tidak ditemukan.');
            }
        }];
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
            'equipment.string' => 'Nama peralatan harus berupa teks.',
            'equipment.max' => 'Nama peralatan maksimal 255 karakter.',
            'detail_equipment.required' => 'Detail peralatan wajib diisi.',
            'detail_equipment.string' => 'Detail peralatan harus berupa teks.',
            'detail_equipment.max' => 'Detail peralatan maksimal 255 karakter.',
            'severity.string' => 'Severity harus berupa teks.',
            'severity.max' => 'Severity maksimal 100 karakter.',
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

            $value = preg_replace('/\s+/u', ' ', trim($input));
            $normalized[$field] = in_array($field, ['equipment', 'severity'], true) && $value === '' ? null : $value;
        }

        $this->merge($normalized);
    }
}
