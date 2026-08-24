<?php

namespace App\Http\Requests;

use App\Models\InventoryStock;
use App\Models\SparePart;
use App\Services\RamsUnitContext;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator as ValidationValidator;

class ShowInventoryStockStateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('viewAny', InventoryStock::class)
            && Gate::forUser($this->user())->allows('viewAny', SparePart::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'unit_kerja_id' => $this->user()->isPusat()
                ? [
                    'required',
                    'integer',
                    Rule::exists('unit_kerjas', 'id')->where(fn ($query) => $query
                        ->where('is_active', true)
                        ->whereNull('deleted_at')),
                ]
                : ['prohibited'],
            'spare_part_id' => [
                'required',
                'integer',
                Rule::exists('spare_parts', 'id')->where(fn ($query) => $query
                    ->where('is_active', true)
                    ->whereNull('deleted_at')),
            ],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (ValidationValidator $validator): void {
            $unitId = app(RamsUnitContext::class)->resolve($this)?->id;
            if (! $unitId || ($this->user()->isPusat() && $this->integer('unit_kerja_id') !== $unitId)) {
                $validator->errors()->add('unit_kerja_id', 'Unit kerja harus sama dengan wilayah RAMS yang sedang aktif.');
            }
        }];
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(response()->json([
            'errors' => $validator->errors()->toArray(),
        ], 422));
    }
}
