<?php

namespace App\Http\Middleware;

use App\Services\RamsUnitContext;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'app' => [
                'name' => config('app.name'),
            ],
            'auth' => [
                'user' => function () use ($request): ?array {
                    $user = $request->user();

                    if (! $user) {
                        return null;
                    }

                    $user->loadMissing('unitKerja:id,code,name');

                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'username' => $user->username,
                        'email' => $user->email,
                        'role' => $user->role->value,
                        'unit_kerja_id' => $user->unit_kerja_id,
                        'unit_kerja' => $user->unitKerja?->only(['id', 'code', 'name']),
                        'is_active' => $user->is_active,
                    ];
                },
            ],
            'active_rams_unit' => function () use ($request): ?array {
                $unit = app(RamsUnitContext::class)->resolve($request);

                return $unit?->only(['id', 'code', 'name']);
            },
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
