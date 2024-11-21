<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\Room;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserReservationController extends Controller
{
    public function create()
    {
        $checkIn = now();
        $checkOut = now()->addHours(12);

        return view("home.form", compact('checkIn', 'checkOut'));
    }


    public function store(Request $request)
    {
        $rules = [
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'pax' => 'required|integer|min:1',
            'contact' => 'required|string|max:50',
            'car_unit_plate_number' => 'nullable|string|max:255',
            'check_in' => 'required|date',
            'check_out' => 'required|date|after:check_in',
            'rooms' => 'required|array',
            'rooms.*' => 'exists:rooms,id',
            'total_amount' => 'required|numeric|min:0',
            'down_payment' => 'required|numeric|min:0',
        ];

        $request->validate($rules);
        try {
            $expected_down_payment = (int)($request->total_amount * 0.20);

            if ($request->down_payment < $expected_down_payment) {
                return back()->with('error', 'Down payment is too low.');
            }

            $reservation = Reservation::create([
                'name' => $request->name,
                'address' => $request->address,
                'pax' => $request->pax,
                'contact' => $request->contact,
                'car_unit_plate_number' => $request->car_unit_plate_number,
                'check_in' => $request->check_in,
                'check_out' => $request->check_out,
                'total_amount' => $request->total_amount,
                'status' => 'pending',
                'down_payment' => $request->down_payment
            ]);

            return redirect()->route('user-form.receipt', [
                'id' => $reservation->id,
                'down_payment' => $request->down_payment
            ])->with('success', 'Reservation created successfully.');
        } catch (Exception $e) {
            Log::error('Error creating reservation', [
                'error_message' => $e->getMessage(),
                'request_data' => $request->all(),
            ]);

            return back()->with('error', 'Failed to create reservation.');
        }
    }

    public function checkAvailability(Request $request)
    {
        try {
            $checkIn = $request->input('check_in');
            $checkOut = $request->input('check_out');
            $stayType = $request->input('stay_type');

            // Logic to get reserved room IDs from the reservation_room pivot table
            $reservedRoomIds = DB::table('reservation_room')
                ->join('reservations', 'reservation_room.reservation_id', '=', 'reservations.id')
                ->where(function ($query) use ($checkIn, $checkOut) {
                    $query->whereBetween('reservations.check_in', [$checkIn, $checkOut])
                        ->orWhereBetween('reservations.check_out', [$checkIn, $checkOut])
                        ->orWhere(function ($query) use ($checkIn, $checkOut) {
                            $query->where('reservations.check_in', '<=', $checkIn)
                                ->where('reservations.check_out', '>=', $checkOut);
                        });
                })
                ->pluck('reservation_room.room_id');

            // Get available rooms that are not in the reserved room IDs and match the stay type
            $availableRooms = Room::whereNotIn('id', $reservedRoomIds)
                ->where('stay_type', $stayType)
                ->get(['id', 'room_number', 'room_type', 'pax', 'price']);

            return response()->json(['rooms' => $availableRooms], 200);
        } catch (\Exception $e) {
            // Log the error
            Log::error('Error in checkAvailability: ' . $e->getMessage());
            return response()->json(['error' => 'Something went wrong'], 500);
        }
    }

    public function receipt($id)
    {
        $reservation = Reservation::findOrFail($id);
        return view('home.receipt', compact('reservation'));
    }
}