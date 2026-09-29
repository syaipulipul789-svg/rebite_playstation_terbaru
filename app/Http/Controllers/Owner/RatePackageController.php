<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\RatePackageRequest;
use App\Models\RatePackage;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RatePackageController extends Controller
{
    public function index(Request $request): View
    {
        $packages = RatePackage::query()
            ->when($request->filled('unit_type'), fn ($q) => $q->where('unit_type', $request->string('unit_type')->toString()))
            ->orderBy('sort_order')
            ->orderBy('duration_minutes')
            ->paginate(20)
            ->withQueryString();

        return view('owner.rate-packages.index', [
            'packages' => $packages,
            'unitTypes' => $this->unitTypes(),
            'filters' => $request->only(['unit_type']),
        ]);
    }

    public function create(): View
    {
        return view('owner.rate-packages.form', [
            'package' => new RatePackage(['duration_minutes' => 60, 'price' => 0, 'is_active' => true, 'sort_order' => 0]),
            'unitTypes' => $this->unitTypes(),
        ]);
    }

    public function store(RatePackageRequest $request): RedirectResponse
    {
        $package = RatePackage::create($request->validated());

        return redirect()->route('owner.rate-packages.index')
            ->with('success', "Paket \"{$package->name}\" berhasil ditambahkan.");
    }

    public function edit(RatePackage $ratePackage): View
    {
        return view('owner.rate-packages.form', [
            'package' => $ratePackage,
            'unitTypes' => $this->unitTypes(),
        ]);
    }

    public function update(RatePackageRequest $request, RatePackage $ratePackage): RedirectResponse
    {
        $ratePackage->update($request->validated());

        return redirect()->route('owner.rate-packages.index')
            ->with('success', "Paket \"{$ratePackage->name}\" berhasil diperbarui.");
    }

    public function destroy(RatePackage $ratePackage): RedirectResponse
    {
        $name = $ratePackage->name;
        $ratePackage->update(['is_active' => false]);

        return back()->with('success', "Paket \"{$name}\" dinonaktifkan.");
    }

    /**
     * @return list<string>
     */
    private function unitTypes(): array
    {
        return Unit::query()
            ->distinct()
            ->orderBy('type')
            ->pluck('type')
            ->all();
    }
}
