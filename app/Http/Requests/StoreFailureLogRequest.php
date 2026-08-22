<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFailureLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_active === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'asset_id' => ['required', 'integer', 'exists:assets,id'],
            'location' => ['required', 'string', 'max:255'],
            'resort' => ['nullable', 'string', 'max:255'],
            'qc' => ['nullable', 'string', 'max:255'],
            'failure_event' => ['required', 'string', 'max:255'],
            'cause' => ['required', 'string', 'max:5000'],
            'action_taken' => ['required', 'string', 'max:5000'],
            'started_at' => ['required', 'date'],
            'resolved_at' => ['required', 'date', 'after_or_equal:started_at'],
            'spare_part_replaced' => ['required', 'boolean'],
            'spare_part_id' => [
                Rule::requiredIf(fn (): bool => $this->boolean('spare_part_replaced')),
                'nullable',
                'integer',
                Rule::exists('spare_parts', 'id')->where(
                    fn ($query) => $query->where('is_active', true)->whereNull('deleted_at'),
                ),
            ],
            'spare_part_quantity' => [
                Rule::requiredIf(fn (): bool => $this->boolean('spare_part_replaced')),
                'nullable',
                'integer',
                'min:1',
            ],
            'vandalism' => ['required', 'boolean'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'asset_id.exists' => 'Aset tidak ditemukan.',
            'resolved_at.after_or_equal' => 'Waktu selesai tidak boleh lebih awal dari waktu kejadian.',
            'spare_part_id.required' => 'Pilih suku cadang yang diganti.',
            'spare_part_id.exists' => 'Suku cadang tidak aktif atau tidak ditemukan.',
            'spare_part_quantity.required' => 'Masukkan jumlah suku cadang yang dipakai.',
            'idempotency_key.uuid' => 'Kunci transaksi Trouble Report tidak valid.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];
        foreach (['location', 'resort', 'qc', 'failure_event', 'cause', 'action_taken'] as $field) {
            if (! $this->exists($field)) {
                continue;
            }

            $value = preg_replace("/\s+/u", ' ', trim($this->string($field)->toString()));
            $normalized[$field] = $value === '' ? null : $value;
        }

        if ($this->exists('idempotency_key')) {
            $normalized['idempotency_key'] = trim($this->string('idempotency_key')->toString());
        }

        $this->merge($normalized);
    }
}
