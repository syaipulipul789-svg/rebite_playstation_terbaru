<?php

namespace App\Http\Controllers\Owner;

use App\Enums\UnitStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\UnitRequest;
use App\Models\AuditLog;
use App\Models\RatePackage;
use App\Models\Unit;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UnitMasterController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $filters = $request->only(['status', 'type', 'q']);

        $units = Unit::query()
            ->withCount(['rentalSessions as completed_sessions' => fn ($q) => $q->where('status', 'COMPLETED')])
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['type'] ?? null, fn ($q, $type) => $q->where('type', $type))
            ->when($filters['q'] ?? null, function ($q, $term) {
                $term = trim((string) $term);

                $q->where(fn ($inner) => $inner
                    ->where('code', 'like', "%{$term}%")
                    ->orWhere('name', 'like', "%{$term}%"));
            })
            ->orderBy('type')
            ->orderBy('code')
            ->paginate(20)
            ->withQueryString();

        $types = Unit::query()->distinct()->orderBy('type')->pluck('type');

        return view('owner.units.index', [
            'units' => $units,
            'types' => $types,
            'packageTypes' => RatePackage::query()
                ->whereNotNull('unit_type')
                ->distinct()
                ->orderBy('unit_type')
                ->pluck('unit_type'),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        return view('owner.units.form', [
            'unit' => new Unit(['status' => UnitStatus::READY, 'hourly_rate' => 0]),
            'types' => Unit::query()->distinct()->orderBy('type')->pluck('type'),
        ]);
    }

    public function store(UnitRequest $request): RedirectResponse
    {
        $unit = Unit::create($request->validated());

        $this->audit->record(
            user: $request->user(),
            shift: null,
            event: AuditLog::EVENT_MASTER_UPDATED,
            description: "Unit baru ditambahkan: {$unit->code} - {$unit->name}",
            context: ['unit_id' => $unit->id],
        );

        return redirect()->route('owner.units.index')
            ->with('success', "Unit {$unit->code} berhasil ditambahkan.");
    }

    public function edit(Unit $unit): View
    {
        return view('owner.units.form', [
            'unit' => $unit,
            'types' => Unit::query()->whereKeyNot($unit->id)->distinct()->orderBy('type')->pluck('type'),
        ]);
    }

    public function update(UnitRequest $request, Unit $unit): RedirectResponse
    {
        $unit->update($request->validated());

        $this->audit->record(
            user: $request->user(),
            shift: null,
            event: AuditLog::EVENT_MASTER_UPDATED,
            description: "Unit {$unit->code} diperbarui",
            context: ['unit_id' => $unit->id, 'data' => $request->validated()],
        );

        return redirect()->route('owner.units.index')
            ->with('success', "Unit {$unit->code} berhasil diperbarui.");
    }

    public function destroy(Request $request, Unit $unit): RedirectResponse
    {
        if ($unit->status === UnitStatus::BUSY) {
            return back()->with('error', "Unit {$unit->code} sedang berjalan, tidak bisa dihapus.");
        }

        $code = $unit->code;
        $unit->delete();

        $this->audit->record(
            user: $request->user(),
            shift: null,
            event: AuditLog::EVENT_MASTER_UPDATED,
            description: "Unit {$code} dihapus",
        );

        return back()->with('success', "Unit {$code} berhasil dihapus.");
    }

    /**
     * Ubah status cepat (Ready / Servis) dari halaman index.
     */
    public function toggleStatus(Request $request, Unit $unit): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:READY,MAINTENANCE'],
        ]);

        $target = UnitStatus::from($validated['status']);

        if ($unit->status === UnitStatus::BUSY) {
            return back()->with('error', "Unit {$unit->code} sedang berjalan. Selesaikan atau batalkan sewanya dulu.");
        }

        $unit->update(['status' => $target]);

        $this->audit->record(
            user: $request->user(),
            shift: null,
            event: AuditLog::EVENT_MASTER_UPDATED,
            description: "Status unit {$unit->code} diubah menjadi {$target->label()}",
            context: ['unit_id' => $unit->id, 'status' => $target->value],
        );

        return back()->with('success', "Status {$unit->code} diubah ke {$target->label()}.");
    }
}
