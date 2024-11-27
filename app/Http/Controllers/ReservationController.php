<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReservationController extends Controller
{
    public function index(Request $request)
    {
        $query = Reservation::query();

        // Search by 'contact', 'name', or 'address'
        if ($request->has('search') && $request->search != '') {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('contact', 'like', '%' . $search . '%')
                    ->orWhere('name', 'like', '%' . $search . '%')
                    ->orWhere('address', 'like', '%' . $search . '%');
            });
        }

        // Filter by month
        if ($request->has('month') && $request->month != '') {
            $query->whereMonth('created_at', $request->month);
        }

        // Filter by day
        if ($request->has('day') && $request->day != '') {
            $query->whereDay('created_at', $request->day);
        }

        // Clone the query to get the count before pagination
        $reservationCount = $query->count();

        // Order by latest created_at
        $query->orderBy('created_at', 'desc');

        // Fetch paginated reservations with associated rooms
        $reservations = $query->paginate(10);

        if ($request->ajax()) {
            return view('reservations.partials.table', compact('reservations', 'reservationCount'))->render();
        }

        return view('reservations.index', compact('reservations', 'reservationCount'));
    }

    public function updateReservation(Request $request, $id)
    {
        $reservation = Reservation::findOrFail($id);

        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'pax' => 'required|integer|min:1',
            'contact' => 'required|string|max:255',
            'car_unit_plate_number' => 'nullable|string|max:255',
            'check_in' => 'required|date|before_or_equal:check_out',
            'check_out' => 'required|date|after_or_equal:check_in',
            'stay_type' => 'required|in:day tour,overnight',
            'rooms' => 'required|array|min:1',
            'rooms.*' => 'exists:rooms,id',
        ]);

        // Calculate the duration of the stay
        $checkIn = \Carbon\Carbon::parse($validatedData['check_in']);
        $checkOut = \Carbon\Carbon::parse($validatedData['check_out']);
        $duration = $checkIn->diffInDays($checkOut) + 1; // Adding 1 to include the check-in day

        // Fetch the selected rooms
        $rooms = Room::whereIn('id', $validatedData['rooms'])->get();

        // Calculate the total price
        $totalPrice = $rooms->sum(function ($room) use ($duration, $validatedData) {
            if ($validatedData['stay_type'] === 'day tour') {
                return $room->day_tour_rate * $duration;
            } else { // Overnight
                return $room->overnight_rate * $duration;
            }
        });

        // Update reservation fields
        $reservation->update(array_merge($validatedData, ['total_price' => $totalPrice]));

        // Update rooms relationship
        $reservation->rooms()->sync($validatedData['rooms']);

        return redirect()
            ->route('reservations.index')
            ->with('success', 'Reservation updated successfully.');
    }


    public function create(Request $request)
    {
        $rooms = Room::all();

        return view('reservations.create', compact('rooms'));
    }

    public function edit(Reservation $reservation)
    {
        $availableRooms = Room::all();
        $selectedRoomIds = $reservation->rooms->pluck('id')->toArray();

        return view('reservations.edit', compact('reservation', 'availableRooms', 'selectedRoomIds'));
    }

    public function show(Reservation $reservation)
    {
        return view('reservations.show', compact('reservation'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'pax' => 'required|integer|min:1',
            'contact' => 'required|string',
            'check_in' => 'required|date',
            'check_out' => 'required|date|after:check_in',
            'status' => 'required|string|in:check in,reserved',
            'rooms' => 'required|array',
            'rooms.*' => 'exists:rooms,id',
        ]);

        $rooms = Room::whereIn('id', $validated['rooms'])->get();
        $totalAmount = $rooms->sum('price');

        Reservation::create([
            'name' => $validated['name'],
            'address' => $validated['address'],
            'pax' => $validated['pax'],
            'contact' => $validated['contact'],
            'car_unit_plate_number' => $request->input('car_unit_plate_number'),
            'check_in' => $validated['check_in'],
            'check_out' => $validated['check_out'],
            'status' => $validated['status'],
            'total_amount' => $totalAmount,
            'down_payment' => round($totalAmount * 0.3),
        ]);

        return redirect()->route('reservations.index')->with('success', 'Reservation created successfully.');
    }


    public function update(Reservation $reservation, Request $request)
    {
        // Validate the request data
        $request->validate([
            'status' => 'required|string|in:check out',
        ]);

        $additionalCharges = 0;

        // Check if the status is 'check out'
        if ($request->input('status') === 'check out') {
            $checkoutTime = now();
            $reservation->check_out = $checkoutTime; // Update the actual check-out time

            // Calculate late check-out fees
            $originalCheckout = $reservation->getOriginal('check_out'); // Get the scheduled check-out time
            if ($originalCheckout) {
                $lateHours = $checkoutTime->diffInHours($originalCheckout, false); // Calculate hours past check-out time

                if ($lateHours > 0) {
                    if ($reservation->pax >= 8 && $reservation->pax <= 15) {
                        $additionalCharges += $lateHours * 1000;
                    } elseif ($reservation->pax >= 2 && $reservation->pax <= 7) {
                        $additionalCharges += $lateHours * 500;
                    }
                }
            }

            // Calculate charges for open cottages or tables
            if (isset($reservation->cottage_type)) {
                if ($reservation->cottage_type === 'day tour') {
                    $additionalCharges += $reservation->pax * 200;
                } elseif ($reservation->cottage_type === 'overnight') {
                    $additionalCharges += $reservation->pax * 300;
                }
            }

            // Save the additional charges to the sales_reports table
            DB::table('sales_reports')->insert([
                'reservation_id' => $reservation->id,
                'amount' => $additionalCharges,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Update the total amount in the reservation
            $reservation->total_amount += $additionalCharges;
        }

        // Update the reservation status
        $reservation->status = $request->input('status');
        $reservation->save();

        return redirect()->route('reservations.index')->with('success', 'Reservation status updated successfully.');
    }

    public function showReceipt($id)
    {
        $reservation = Reservation::with('rooms')->findOrFail($id);

        return view('reservations.receipt', compact('reservation'));
    }

    /**
     * Apply commission to the reservation.
     *
     * @param int $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function applyCommission($id)
    {
        // Find the reservation
        $reservation = Reservation::findOrFail($id);

        // Check if it's already commissioned
        if ($reservation->is_commissioned) {
            return redirect()->back()->with('message', 'Commission already applied.');
        }

        // Get the commission percent from settings
        $commissionPercent = Setting::where('key', 'commission_percent')->value('value') ?? 10;

        // Calculate commission amount
        $commissionAmount = $reservation->total_amount * ($commissionPercent / 100);

        // Update the commission amount and total amount
        $reservation->commission_amount = $commissionAmount;
        $reservation->total_amount -= $commissionAmount;
        $reservation->is_commissioned = true;
        $reservation->save();

        // Add the commission amount as an expense
        Expense::create([
            'expense_description' => "Commission for Reservation ID: $reservation->id",
            'amount' => $commissionAmount,
            'date_time' => now(),
        ]);

        return redirect()->back()->with('message', 'Commission applied successfully.');
    }

    public function updateRooms(Request $request)
    {
        $checkIn = $request->input('check_in');
        $checkOut = $request->input('check_out');
        $stayType = $request->input('stay_type');

        $today = now()->toDateString();

        $request->validate([
            'check_in' => ['required', 'date', 'after_or_equal:' . $today],
            'check_out' => ['required', 'date', 'after:check_in'],
            'stay_type' => ['nullable', 'in:day tour,overnight'], // Add validation for stay type
        ]);

        // Get reserved rooms between check-in and check-out dates
        $reservedRoomIds = Reservation::where(function ($query) use ($checkIn, $checkOut) {
            $query->whereBetween('check_in', [$checkIn, $checkOut])
                ->orWhereBetween('check_out', [$checkIn, $checkOut])
                ->orWhere(function ($query) use ($checkIn, $checkOut) {
                    $query->where('check_in', '<=', $checkIn)
                        ->where('check_out', '>=', $checkOut);
                });
        })->with('rooms')->get()->pluck('rooms.*')->flatten()->pluck('id')->toArray();

        // Get rooms that are not reserved, optionally filter by stay type
        $query = Room::whereNotIn('id', $reservedRoomIds);

        if ($stayType) {
            $query->where('stay_type', $stayType);
        }

        $rooms = $query->get();

        // Return available rooms as JSON
        return response()->json(['rooms' => $rooms]);
    }
}
