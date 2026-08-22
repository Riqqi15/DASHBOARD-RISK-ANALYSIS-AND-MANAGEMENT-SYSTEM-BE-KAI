<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateRegionalAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isPusat() ?? false;
    }

    public function rules(): array
    {
        $currentUnitId = $this->route('account')?->unit_kerja_id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => [
                'required',
                'string',
                'min:3',
                'max:50',
                "regex:/\A[a-z0-9._-]+\z/",
                Rule::unique('users', 'username')->ignore($this->route('account')),
            ],
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->route('account')),
            ],
            'unit_kerja_id' => [
                'required',
                Rule::exists('unit_kerjas', 'id')->where(
                    fn ($query) => $query
                        ->whereNull('deleted_at')
                        ->where(
                            fn ($units) => $units
                                ->where('is_active', true)
                                ->when($currentUnitId, fn ($current) => $current->orWhere('id', $currentUnitId)),
                        ),
                ),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'username' => Str::lower(trim($this->string('username')->toString())),
        ]);
    }
}
