<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUnitSubsystemOpeningRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isPusat() === true;
    }

    public function rules(): array
    {
        return [
            'sparepart_in' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'sparepart_out' => ['required', 'integer', 'min:0', 'max:4294967295'],
        ];
    }
}
