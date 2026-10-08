<?php

namespace App\Http\Controllers;

use App\Enums\RentalRequestStatus;
use App\Http\Requests\CompleteRentalRequestRequest;
use App\Models\RentalRequest;
use App\Services\RentalRequestService;
use App\Support\AvailabilityConflict;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use InvalidArgumentException;

class PosRentalRequestController extends Controller
{
    public function __construct(private readonly RentalRequestService $rentals) {}

    public function index(Request $request): View
    {
        $rentalRequests = RentalRequest::query()
            ->with(['unit', 'package', 'confirmer', 'user'])
            ->orderByDesc('created_at')
            ->paginate(50);

        $pendingCount = RentalRequest::where('status', RentalRequestStatus::PENDING)->count();

        return view('pos.rental-requests.index', [
            'rentalRequests' => $rentalRequests,
            'pendingCount' => $pendingCount,
        ]);
    }

    public function confirm(RentalRequest $rentalRequest): RedirectResponse
    {
        return $this->perform(
            fn () => $this->rentals->confirm($rentalRequest, Auth::user()),
            'dikonfirmasi',
        );
    }

    public function cancel(RentalRequest $rentalRequest): RedirectResponse
    {
        return $this->perform(
            fn () => $this->rentals->cancel($rentalRequest),
            'dibatalkan',
        );
    }

    public function complete(CompleteRentalRequestRequest $request, RentalRequest $rentalRequest): RedirectResponse
    {
        return $this->perform(
            fn () => $this->rentals->complete($rentalRequest, $request->validated()),
            'selesai',
        );
    }

    /**
     * Jalankan aksi kasir terhadap satu permintaan sewa. Status yang tidak
     * sesuai atau unit yang tiba-tiba bentrok jadwalnya ditangkap dan
     * dikembalikan sebagai pesan error, bukan error 500.
     */
    private function perform(callable $action, string $successSuffix): RedirectResponse
    {
        try {
            $rentalRequest = $action();
        } catch (AvailabilityConflict|InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Permintaan sewa {$rentalRequest->rentalRequestCode()} {$successSuffix}.");
    }
}
