<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\ContactRequest;
use App\Jobs\SendContactNotification;
use App\Models\ContactUs;
use Illuminate\Http\JsonResponse;

class ContactController extends Controller
{
    /**
     * Store a contact form submission.
     * Validates input, stores in database, and notifies administrators.
     */
    public function store(ContactRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            // Store the inquiry
            $contact = ContactUs::create($validated);

            // Notify admins via queue (non-blocking)
            SendContactNotification::dispatch($contact);

            return response()->json([
                'success' => true,
                'message' => 'Thank you for your message. We will get back to you soon.',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit contact form',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
