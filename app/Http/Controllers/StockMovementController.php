<?php

namespace App\Http\Controllers;

use App\Enums\StockDirection;
use App\Enums\StockMovementType;
use App\Http\Requests\CorrectStockMovementRequest;
use App\Http\Requests\ShowInventoryStockStateRequest;
use App\Http\Requests\StoreStockMovementRequest;
use App\Models\InventoryStock;
use App\Models\SparePart;
use App\Models\StockMovement;
use App\Models\UnitKerja;
use App\Services\StockMovementService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class StockMovementController extends Controller
{
    public function __construct(private readonly StockMovementService $movements) {}

    public function state(ShowInventoryStockStateRequest $request): JsonResponse
    {
        $user = $request->user();
        $unitId = $user->isPusat() ? (int) $request->validated('unit_kerja_id') : (int) $user->unit_kerja_id;
        $partId = (int) $request->validated('spare_part_id');
        $stock = InventoryStock::query()
            ->visibleTo($user)
            ->where('unit_kerja_id', $unitId)
            ->where('spare_part_id', $partId)
            ->first();
        $quantity = (int) ($stock?->quantity ?? 0);
        $hasMovement = StockMovement::query()
            ->visibleTo($user)
            ->where('unit_kerja_id', $unitId)
            ->where('spare_part_id', $partId)
            ->exists();

        return response()->json([
            'quantity' => $quantity,
            'can_open' => $quantity === 0 && ! $hasMovement,
            'can_out' => $stock !== null && $quantity > 0,
        ]);
    }

    public function store(StoreStockMovementRequest $request): RedirectResponse
    {
        $user = $request->user();
        $unitId = $user->isPusat() ? (int) $request->validated('unit_kerja_id') : (int) $user->unit_kerja_id;
        $unit = UnitKerja::query()->findOrFail($unitId);
        $part = SparePart::query()->active()->findOrFail($request->integer('spare_part_id'));

        $stock = InventoryStock::query()->visibleTo($user)->whereBelongsTo($unit)->whereBelongsTo($part)->first();
        if ($stock) {
            Gate::authorize('createMovement', $stock);
        } else {
            Gate::authorize('viewAny', InventoryStock::class);
        }

        $this->movements->record(
            unit: $unit,
            part: $part,
            actor: $user,
            type: StockMovementType::from($request->string('type')->toString()),
            direction: StockDirection::from($request->string('direction')->toString()),
            quantity: $request->integer('quantity'),
            movementDate: CarbonImmutable::parse($request->string('movement_date')->toString()),
            referenceNumber: $request->validated('reference_number'),
            notes: $request->validated('notes'),
            idempotencyKey: $request->string('idempotency_key')->toString(),
        );

        return redirect('/inventory')->with('success', 'Transaksi stok berhasil dicatat.');
    }

    public function correct(CorrectStockMovementRequest $request): RedirectResponse
    {
        $source = $request->sourceMovement();
        Gate::authorize('correct', $source);

        $unit = UnitKerja::query()->findOrFail($source->unit_kerja_id);
        $part = SparePart::query()->findOrFail($source->spare_part_id);

        $this->movements->record(
            unit: $unit,
            part: $part,
            actor: $request->user(),
            type: StockMovementType::Correction,
            direction: StockDirection::from($request->string('direction')->toString()),
            quantity: $request->integer('quantity'),
            movementDate: CarbonImmutable::parse($request->string('movement_date')->toString()),
            referenceNumber: null,
            notes: $request->validated('notes'),
            idempotencyKey: $request->string('idempotency_key')->toString(),
            reverses: $source,
        );

        return redirect('/inventory')->with('success', 'Koreksi stok berhasil dicatat.');
    }
}
