<?php

namespace App\Http\Requests;

use App\Enums\StockDirection;
use App\Enums\StockMovementType;
use App\Models\StockMovement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CorrectStockMovementRequest extends FormRequest
{
    private ?StockMovement $sourceMovement = null;

    public function authorize(): bool
    {
        $this->sourceMovement = StockMovement::query()
            ->visibleTo($this->user())
            ->findOrFail($this->route('movement'));

        return Gate::forUser($this->user())->allows('correct', $this->sourceMovement);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'unit_kerja_id' => ['prohibited'],
            'spare_part_id' => ['prohibited'],
            'type' => ['prohibited'],
            'direction' => ['required', Rule::enum(StockDirection::class)],
            'quantity' => ['required', 'integer', 'min:1'],
            'movement_date' => ['required', 'date', 'before_or_equal:today'],
            'reference_number' => ['prohibited'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->sourceMovement()->type === StockMovementType::Correction) {
                $validator->errors()->add('movement', 'Transaksi koreksi tidak dapat dikoreksi kembali.');
            }
        }];
    }

    public function sourceMovement(): StockMovement
    {
        return $this->sourceMovement ?? throw new \LogicException('Transaksi sumber belum diselesaikan.');
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'unit_kerja_id.prohibited' => 'Unit kerja koreksi selalu mengikuti transaksi sumber.',
            'spare_part_id.prohibited' => 'Suku cadang koreksi selalu mengikuti transaksi sumber.',
            'type.prohibited' => 'Jenis koreksi ditentukan oleh sistem.',
            'reference_number.prohibited' => 'Nomor referensi koreksi mengikuti tautan transaksi sumber.',
            'quantity.min' => 'Jumlah koreksi minimal 1.',
            'movement_date.before_or_equal' => 'Tanggal koreksi tidak boleh melewati hari ini.',
            'notes.max' => 'Catatan maksimal 1000 karakter.',
            'idempotency_key.uuid' => 'Kunci transaksi tidak valid. Tutup lalu buka kembali formulir.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];
        if ($this->exists('notes')) {
            $value = preg_replace('/\s+/u', ' ', trim($this->string('notes')->toString()));
            $normalized['notes'] = $value === '' ? null : $value;
        }
        if ($this->exists('idempotency_key')) {
            $normalized['idempotency_key'] = trim($this->string('idempotency_key')->toString());
        }

        $this->merge($normalized);
    }
}
