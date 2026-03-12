<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Guest;
// use Carbon\Carbon;

class GuestController extends Controller
{
    public function index()
    {
        try {
            $guests = Guest::all();
            return response()->json($guests);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve guests',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

        public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'first_name'       => 'required|string|max:100',
                'middle_name'      => 'nullable|string|max:100',
                'last_name'        => 'required|string|max:100',
                'email'            => 'required|email|unique:guests,email',
                'contact_num'      => 'required|string|max:20',

                'gender'           => 'nullable|in:male,female,other',

                'is_international' => 'required|boolean',
                'country'          => 'nullable|string|max:100',

                // PH Address
                'region'           => 'nullable|string|max:100',
                'province'         => 'nullable|string|max:100',
                'municipality'     => 'nullable|string|max:100',
                'barangay'         => 'nullable|string|max:100',

            ]);

            // Default country logic
            if (!$validated['is_international']) {
                $validated['country'] = 'Philippines';
            }

            $guest = Guest::create($validated);

            return response()->json([
                'message' => 'Guest successfully created!',
                'data' => $guest,
                'guest_id' => $guest->id
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create guest',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function destroy($id)
    {
        try {
            $guest = Guest::find($id);

            if (!$guest) {
                return response()->json(['message' => 'Guest Record not found.'], 404);
            }

            $guest->delete();

            return response()->json(['message' => 'Guest record deleted successfully.']);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete guest',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}