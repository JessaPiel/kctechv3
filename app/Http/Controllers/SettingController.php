<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class SettingController extends Controller
{
    public function index()
    {
        return view('settings.index');
    }

    public function changePassword()
    {
        return view('settings.password');
    }

    public function editEmail()
    {
        if (Auth::check() && Auth::user()->role === 'admin') {
            // Retrieve the current email from the settings or directly from Auth
            $emailSetting = Auth::user(); // This gives the authenticated admin's user instance

            return view('settings.edit-email', compact('emailSetting'));
        }

        return redirect()->route('home')->with('error', 'You do not have permission to access this page.');
    }

    public function updateEmail(Request $request)
    {
        if (Auth::check() && Auth::user()->role === 'admin') {
            // Validate the email input
            $validated = $request->validate([
                'email' => 'required|email|unique:users,email,' . Auth::id(),
            ]);

            // Update the authenticated user's email
            $user = Auth::user();
            $user->email = $validated['email'];
            $user->save();

            // Redirect back with success message
            return redirect()->route('settings.updateEmail')->with('success', 'Email updated successfully.');
        }

        return redirect()->route('home')->with('error', 'You do not have permission to access this page.');
    }

    public function updatePassword(Request $request)
    {

        $request->validate([
            'current_password' => ['required'],
            'new_password' => ['required', 'confirmed'],
        ]);

        if (!Hash::check($request->current_password, Auth::user()->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'The current password is incorrect.',
            ]);
        }

        Auth::user()->update([
            'password' => Hash::make($request->new_password),
        ]);

        return redirect()->route('settings')->with('success', 'Password changed successfully.');
    }

    public function edit()
    {
        $settings = DB::table('settings')->pluck('value', 'key')->toArray();
        return view('settings.edit', compact('settings'));
    }

    public function update(Request $request)
    {
        $currentYear = now()->year;

        $request->validate([
            'total_revenue_year' => "integer|lte:$currentYear",
            'total_expenses_year' => "integer|lte:$currentYear",
            'total_salaries_year' => "integer|lte:$currentYear",
            'predict_sales_month' => 'integer|between:1,12',
            'historical_sales_data' => 'integer|between:1,12',
            'predict_reservations_month' => 'integer|between:1,12',
            'historical_reservations_data' => 'integer|between:1,12',
        ]);

        $settings = $request->except('_token');

        foreach ($settings as $key => $value) {
            DB::table('settings')->where('key', $key)->update(['value' => $value]);
        }

        return redirect()->route('settings.edit')->with('success', 'Settings updated successfully.');
    }
}
