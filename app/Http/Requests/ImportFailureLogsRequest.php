<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\UnitKerja;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

final class ImportFailureLogsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $unitRules = $this->user()?->isPusat()
            ? [
                'required',
                'integer',
                Rule::exists('unit_kerjas', 'id')->where('is_active', true)->whereNull('deleted_at'),
            ]
            : ['nullable', 'integer'];

        return [
            'unit_kerja_id' => $unitRules,
            'workbook' => [
                'required',
                File::types(['xlsx', 'xlsm'])->max(50 * 1024),
            ],
        ];
    }

    public function selectedUnit(): UnitKerja
    {
        $user = $this->user();

        if ($user?->isUnit()) {
            return UnitKerja::query()
                ->whereKey($user->unit_kerja_id)
                ->where('is_active', true)
                ->firstOrFail();
        }

        return UnitKerja::query()
            ->whereKey((int) $this->validated('unit_kerja_id'))
            ->where('is_active', true)
            ->firstOrFail();
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'unit_kerja_id.required' => 'Pilih unit kerja tujuan impor.',
            'unit_kerja_id.exists' => 'Unit kerja tujuan tidak aktif atau tidak ditemukan.',
            'workbook.required' => 'Pilih file workbook RAMS.',
            'workbook.mimes' => 'File harus berformat .xlsm atau .xlsx.',
            'workbook.max' => 'Ukuran workbook maksimal 50 MB.',
        ];
    }
}
