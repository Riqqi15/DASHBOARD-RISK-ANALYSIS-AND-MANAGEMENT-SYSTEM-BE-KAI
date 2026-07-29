<?php

namespace App\Http\Requests\Admin;

use App\Models\SparePart;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateSparePartRequest extends StoreSparePartRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('update', $this->route('spare_part'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var SparePart $part */
        $part = $this->route('spare_part');
        $rules = parent::rules();
        $rules['code'] = ['required', 'string', 'max:50', Rule::unique('spare_parts', 'code')->ignore($part)];

        return $rules;
    }
}
