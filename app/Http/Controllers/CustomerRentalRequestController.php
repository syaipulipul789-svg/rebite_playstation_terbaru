<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateRentalRequestRequest;
use App\Models\RatePackage;
use App\Models\RentalRequest;
use App\Models\Unit;
use App\Services\RentalRequestService;
use App\Support\AvailabilityConflict;
use App\Support\CustomerAvailability;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class CustomerRentalRequestController extends Controller
{
    public function __construct(private readonly RentalRequestService $rentals) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        $rentalRequests = RentalRequest::query()
            ->where('user_id', $user->id)
            ->with(['unit', 'package'])
            ->orderByDesc('created_at')
            ->paginate(20);

        $units = Unit::query()
            ->with(['confirmedBookings' => fn ($q) => $q->with('package'), 'runningSession'])
            ->orderBy('name')
            ->get();

        return view('customer.rentals.index', [
            'rentalRequests' => $rentalRequests,
            'units' => CustomerAvailability::bookingUnits($units),
            'ratePackages' => RatePackage::query()->orderBy('duration_minutes')->get(),
        ]);
    }

    public function store(CreateRentalRequestRequest $form): RedirectResponse
    {
        $data = $form->customer();
        $customer = $form->user();

        try {
            $req = $this->rentals->createForCustomer(
                customer: $customer,
                unit: Unit::findOrFail($form->integer('unit_id')),
                package: $form->integer('package_id') ? RatePackage::find($form->integer('package_id')) : null,
                startTime: Carbon::parse($form->string('start_time')),
                endTime: Carbon::parse($form->string('end_time')),
                notes: $form->string('notes') ?? null,
            );
        } catch (AvailabilityConflict $e) {
            return back()->withInput()->withErrors(['unit_id' => $e->getMessage()]);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['end_time' => $e->getMessage()]);
        }

        return redirect()
            ->route('customer.rentals.index')
            ->with('success', 'Permintaan sewa '.$req->rentalRequestCode().' berhasil dikirim. Menunggu konfirmasi kasir.');
    }
}
