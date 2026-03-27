<?php

use App\Http\Controllers\API\BlockedDateController;
use App\Http\Controllers\API\BlogPostController;
use App\Http\Controllers\API\BookingController;
use App\Http\Controllers\API\ContactController;
use App\Http\Controllers\API\GalleryController;
use App\Http\Controllers\API\ReviewController;
use App\Http\Controllers\API\RoomController;
use App\Http\Controllers\API\VenueController;
use App\Http\Middleware\EnsureApiKeyIsValid;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/health', function () {
    try {
        DB::connection()->getPdo();

        return response()->json(['status' => 'ok', 'database' => 'connected'], 200);
    } catch (Throwable $e) {
        return response()->json(['status' => 'error', 'database' => 'disconnected'], 503);
    }
});

Route::middleware([EnsureApiKeyIsValid::class])->group(function () {
    Route::middleware('throttle:api')->group(function () {
        // Bookings (stricter limit on create and review)
        Route::get('bookings', [BookingController::class, 'index']);
        Route::post('bookings', [BookingController::class, 'store'])->middleware('throttle:bookings');
        Route::get('bookings/{id}', [BookingController::class, 'show']);
        Route::put('bookings/{id}', [BookingController::class, 'update']);
        Route::delete('bookings/{id}', [BookingController::class, 'destroy']);
        Route::post('/bookings/{booking:reference_number}/otp/send', [BookingController::class, 'sendBookingOtp'])
            ->middleware('throttle:booking_otp');
        Route::patch('/bookings/{booking:reference_number}/cancel', [BookingController::class, 'cancel']);
        Route::get('bookings/reference/{reference}', [BookingController::class, 'showByReferenceNumber']);
        Route::post('bookings/reference/{reference}/review', [ReviewController::class, 'storeByBookingReference'])->middleware('throttle:bookings');
        Route::patch('/bookings/{reference}/reschedule', [BookingController::class, 'reschedule']);
        // Venues
        Route::get('/venues', [VenueController::class, 'index']);
        Route::get('/venues/{id}', [VenueController::class, 'show']);

        // Rooms
        Route::get('rooms', [RoomController::class, 'index']);
        Route::get('/rooms/{id}', [RoomController::class, 'show']);

        // Blocked Dates
        Route::get('/blocked-dates', [BlockedDateController::class, 'index']);

        // Contact form (stricter limit)
        Route::post('/contact', [ContactController::class, 'store'])->middleware('throttle:contact');

        // Gallery
        Route::get('/galleries', [GalleryController::class, 'index']);
        Route::get('/galleries/{id}', [GalleryController::class, 'show']);

        // Blog (Facebook embed posts)
        Route::get('/blog-posts', [BlogPostController::class, 'index']);
        Route::get('/blog-posts/{slug}', [BlogPostController::class, 'show'])
            ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*');

        Route::get('/reviews', [ReviewController::class, 'index']);
    });
});
