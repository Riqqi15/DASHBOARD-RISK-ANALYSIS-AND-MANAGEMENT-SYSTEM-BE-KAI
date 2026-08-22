<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\UnitKerja;
use App\Services\RamsUnitDetector;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Validator;

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
                'nullable',
                'integer',
                Rule::exists('unit_kerjas', 'id')->where('is_active', true)->whereNull('deleted_at'),
            ]
            : ['nullable', 'integer'];

        return [
            'unit_kerja_id' => $unitRules,
            'workbook' => ['required', File::types(['xlsx', 'xlsm'])->max(50 * 1024)],
            'dry_run' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $workbook = $this->file('workbook');
                if ($workbook === null || ! $workbook->isValid()) {
                    return;
                }

                $detectedCode = app(RamsUnitDetector::class)->detectCode($workbook->getClientOriginalName());
                $detectedUnit =
                    $detectedCode === null
                        ? null
                        : UnitKerja::query()->where('code', $detectedCode)->where('is_active', true)->first();
                $user = $this->user();

                if ($user?->isUnit()) {
                    $assignedCode = $user->unitKerja()->value('code');
                    if ($detectedCode !== null && $detectedCode !== $assignedCode) {
                        $validator
                            ->errors()
                            ->add(
                                'workbook',
                                "Workbook terdeteksi untuk {$detectedCode}, bukan unit akun {$assignedCode}.",
                            );
                    }

                    return;
                }

                $selectedId = $this->integer('unit_kerja_id');
                if ($detectedUnit !== null && $selectedId > 0 && $selectedId !== $detectedUnit->id) {
                    $validator
                        ->errors()
                        ->add(
                            'unit_kerja_id',
                            "Nama workbook terdeteksi sebagai {$detectedCode}; unit tujuan harus sama.",
                        );
                }
                if ($detectedUnit === null && $selectedId < 1) {
                    $validator
                        ->errors()
                        ->add(
                            'unit_kerja_id',
                            'Nama workbook tidak memuat DAOP/DIVRE yang dikenali. Pilih unit tujuan secara manual.',
                        );
                }
                if ($detectedCode !== null && $detectedUnit === null) {
                    $validator
                        ->errors()
                        ->add('unit_kerja_id', "Unit {$detectedCode} tidak aktif atau tidak ditemukan.");
                }
            },
        ];
    }

    public function selectedUnit(RamsUnitDetector $detector): UnitKerja
    {
        $user = $this->user();

        if ($user?->isUnit()) {
            return UnitKerja::query()->whereKey($user->unit_kerja_id)->where('is_active', true)->firstOrFail();
        }

        $workbook = $this->file('workbook');
        $detectedCode = $workbook === null ? null : $detector->detectCode($workbook->getClientOriginalName());
        if ($detectedCode !== null) {
            return UnitKerja::query()->where('code', $detectedCode)->where('is_active', true)->firstOrFail();
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
            'unit_kerja_id.exists' => 'Unit kerja tujuan tidak aktif atau tidak ditemukan.',
            'workbook.required' => 'Pilih file workbook RAMS.',
            'workbook.mimes' => 'File harus berformat .xlsm atau .xlsx.',
            'workbook.max' => 'Ukuran workbook maksimal 50 MB.',
        ];
    }
}
