<?php

namespace App\Http\Requests;

use App\Models\InventoryStock;
use App\Models\SparePart;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

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

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(response()->json([
            'errors' => $validator->errors()->toArray(),
        ], 422));
    }
}
