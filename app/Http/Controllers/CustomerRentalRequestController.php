<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateRentalRequestRequest;
use App\Models\RatePackage;
use App\Models\Unit;
use App\Services\RentalRequestService;
use App\Support\AvailabilityConflict;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class CustomerRentalRequestController extends Controller
{
    public function __construct(private readonly RentalRequestService $rentals) {}

    public function store(CreateRentalRequestRequest $form): JsonResponse|RedirectResponse
    {
        $customer = $form->user();

        try {
            $req = $this->rentals->createForCustomer(
                customer: $customer,
                unit: Unit::findOrFail($form->integer('unit_id')),
                package: $form->integer('package_id') ? RatePackage::find($form->integer('package_id')) : null,
                startTime: Carbon::parse($form->string('start_time')),
                endTime: Carbon::parse($form->string('end_time')),
                notes: $form->string('notes') ?? null,
            )->load('unit');
        } catch (AvailabilityConflict $e) {
            return $form->wantsJson()
                ? response()->json(['message' => $e->getMessage()], 422)
                : back()->withInput()->withErrors(['unit_id' => $e->getMessage()]);
        } catch (InvalidArgumentException $e) {
            return $form->wantsJson()
                ? response()->json(['message' => $e->getMessage()], 422)
                : back()->withInput()->withErrors(['end_time' => $e->getMessage()]);
        }

        $payload = array_merge(
            ['message' => 'Permintaan sewa '.$req->rentalRequestCode().' berhasil dikirim. Menunggu konfirmasi kasir.'],
            $req->customerSnapshot(),
        );

        if ($form->expectsJson()) {
            return response()->json($payload, 201);
        }

        return redirect()
            ->route('customer.dashboard')
            ->with('success', $payload['message']);
    }
}
