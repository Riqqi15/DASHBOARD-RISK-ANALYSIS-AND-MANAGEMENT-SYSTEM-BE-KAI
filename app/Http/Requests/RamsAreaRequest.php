<?php

namespace App\Http\Requests;

use App\Models\UnitKerja;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RamsAreaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_active === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'area' => [
                Rule::prohibitedIf(fn (): bool => $this->user()?->isUnit() === true),
                'nullable',
                'string',
                'max:20',
            ],
            'subsystem' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function selectedUnit(): ?UnitKerja
    {
        $user = $this->user();
        if ($user?->isUnit()) {
            return $user->unitKerja()->where('is_active', true)->firstOrFail();
        }

        $area = $this->validated('area');
        if (! $area) {
            return null;
        }

        return UnitKerja::query()
            ->where('code', $this->databaseCode($area))
            ->where('is_active', true)
            ->firstOrFail();
    }

    private function databaseCode(string $area): string
    {
        $normalized = strtoupper(str_replace(['-', ' '], '', trim($area)));
        if (preg_match('/^DAOP(\d+)$/', $normalized, $matches) === 1) {
            return 'DAOP-'.$matches[1];
        }

        $roman = ['1' => 'I', '2' => 'II', '3' => 'III', '4' => 'IV'];
        if (preg_match('/^DIVRE([1-4])$/', $normalized, $matches) === 1) {
            return 'DIVRE-'.$roman[$matches[1]];
        }
        if (preg_match('/^DIVRE(I|II|III|IV)$/', $normalized, $matches) === 1) {
            return 'DIVRE-'.$matches[1];
        }

        return strtoupper(trim($area));
    }
}
