<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ResetRegionalAccountPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isPusat() ?? false;
    }

    public function rules(): array
    {
        return ['password' => ['required', 'confirmed', Password::min(12)]];
    }
}
