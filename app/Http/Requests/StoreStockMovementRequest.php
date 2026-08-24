<?php

namespace App\Http\Requests;

use App\Enums\StockDirection;
use App\Enums\StockMovementType;
use App\Models\InventoryStock;
use App\Models\SparePart;
use App\Services\RamsUnitContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreStockMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('viewAny', InventoryStock::class) &&
            Gate::forUser($this->user())->allows('viewAny', SparePart::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'unit_kerja_id' => $this->user()->isPusat()
                ? [
                    'required',
                    'integer',
                    Rule::exists('unit_kerjas', 'id')->where(
                        fn ($query) => $query->where('is_active', true)->whereNull('deleted_at'),
                    ),
                ]
                : ['prohibited'],
            'spare_part_id' => [
                'required',
                'integer',
                Rule::exists('spare_parts', 'id')->where(
                    fn ($query) => $query->where('is_active', true)->whereNull('deleted_at'),
                ),
            ],
            'type' => [
                'required',
                Rule::enum(StockMovementType::class),
                Rule::notIn([StockMovementType::Correction->value]),
            ],
            'direction' => ['required', Rule::enum(StockDirection::class)],
            'quantity' => ['required', 'integer', 'min:1'],
            'movement_date' => ['required', 'date', 'before_or_equal:today'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $unitId = app(RamsUnitContext::class)->resolve($this)?->id;
                if (! $unitId) {
                    $validator->errors()->add('unit_kerja_id', 'Wilayah RAMS aktif tidak tersedia.');

                    return;
                }

                if ($this->user()->isPusat() && $this->integer('unit_kerja_id') !== $unitId) {
                    $validator->errors()->add(
                        'unit_kerja_id',
                        'Unit kerja harus sama dengan wilayah RAMS yang sedang aktif.',
                    );
                }

                $type = StockMovementType::tryFrom($this->string('type')->toString());
                $direction = StockDirection::tryFrom($this->string('direction')->toString());
                if (! $type || ! $direction || $type === StockMovementType::Correction) {
                    return;
                }

                $expected = $type === StockMovementType::Out ? StockDirection::Out : StockDirection::In;
                if ($direction !== $expected) {
                    $validator->errors()->add('direction', 'Arah transaksi tidak sesuai dengan jenis transaksi.');
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'unit_kerja_id.required' => 'Pilih unit kerja untuk transaksi stok.',
            'unit_kerja_id.integer' => 'Unit kerja yang dipilih tidak valid.',
            'unit_kerja_id.prohibited' => 'Akun wilayah tidak boleh mengubah unit kerja transaksi.',
            'unit_kerja_id.exists' => 'Unit kerja tidak aktif atau tidak ditemukan.',
            'spare_part_id.required' => 'Pilih suku cadang.',
            'spare_part_id.integer' => 'Suku cadang yang dipilih tidak valid.',
            'spare_part_id.exists' => 'Suku cadang tidak aktif, terhapus, atau tidak ditemukan.',
            'type.required' => 'Pilih jenis transaksi.',
            'type.enum' => 'Jenis transaksi tidak valid.',
            'type.not_in' => 'Koreksi hanya dapat dicatat melalui transaksi sumber.',
            'direction.required' => 'Pilih arah transaksi.',
            'direction.enum' => 'Arah transaksi tidak valid.',
            'quantity.required' => 'Masukkan jumlah transaksi.',
            'quantity.integer' => 'Jumlah transaksi harus berupa bilangan bulat.',
            'quantity.min' => 'Jumlah transaksi minimal 1.',
            'movement_date.required' => 'Pilih tanggal transaksi.',
            'movement_date.date' => 'Tanggal transaksi tidak valid.',
            'movement_date.before_or_equal' => 'Tanggal transaksi tidak boleh melewati hari ini.',
            'reference_number.string' => 'Nomor referensi harus berupa teks.',
            'reference_number.max' => 'Nomor referensi maksimal 100 karakter.',
            'notes.string' => 'Catatan harus berupa teks.',
            'notes.max' => 'Catatan maksimal 1000 karakter.',
            'idempotency_key.required' => 'Kunci transaksi tidak tersedia. Tutup lalu buka kembali formulir.',
            'idempotency_key.uuid' => 'Kunci transaksi tidak valid. Tutup lalu buka kembali formulir.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];
        foreach (['reference_number', 'notes'] as $field) {
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
