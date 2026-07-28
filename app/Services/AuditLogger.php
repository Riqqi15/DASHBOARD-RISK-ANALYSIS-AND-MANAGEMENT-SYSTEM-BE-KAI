<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use LogicException;

class AuditLogger
{
    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public function record(string $action, Model $subject, array $before, array $after, ?User $actor = null): AuditLog
    {
        if ($actor !== null && (! $actor->exists || $actor->getKey() === null)) {
            throw new LogicException('Actor audit eksplisit harus sudah tersimpan.');
        }

        $unitId = $subject instanceof UnitKerja
            ? $subject->getKey()
            : ($subject->getAttribute('unit_kerja_id') ?? Auth::user()?->unit_kerja_id);
        $actorId = $actor === null ? Auth::id() : $actor->getKey();

        return AuditLog::query()->create([
            'actor_id' => $actorId,
            'action' => $action,
            'auditable_type' => $subject->getMorphClass(),
            'auditable_id' => $subject->getKey(),
            'unit_kerja_id' => $unitId,
            'old_values' => $this->withoutSensitiveValues($before),
            'new_values' => $this->withoutSensitiveValues($after),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function withoutSensitiveValues(array $values): array
    {
        return array_filter(
            $values,
            fn (string $key): bool => ! preg_match('/password|remember_token|session(_payload)?|token/i', $key),
            ARRAY_FILTER_USE_KEY,
        );
    }
}
