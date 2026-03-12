<?php

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Response;

Route::get('/qr-image/{filename}', function (string $filename) {
    $filename = basename($filename);
    if ($filename === '' || str_contains($filename, '/') || str_contains($filename, '\\')) {
        abort(404);
    }
    $path = "qr/bookings/{$filename}";

    if (!Storage::disk('public')->exists($path)) {
        abort(404);
    }

    $file = Storage::disk('public')->get($path);
    $type = Storage::disk('public')->mimeType($path);

    return Response::make($file, 200, [
        'Content-Type' => $type,
        'Access-Control-Allow-Origin' => '*',
    ]);
});

Route::redirect('/', '/login');


// Signed link from testimonial email: redirects to client app testimonial form.
Route::get('/testimonial/feedback/{reference}', function (string $reference) {
    $base = rtrim(config('app.frontend_url'), '/');
    return redirect($base . '/testimonial?reference=' . urlencode($reference));
})->name('testimonial.feedback.redirect')->middleware('signed');

if ($adminPanel = Filament::getPanel('admin')) {
    $loginMiddleware = array_merge($adminPanel->getMiddleware(), ['guest']);

    Route::middleware($loginMiddleware)->group(function () use ($adminPanel): void {
        Route::get('/login', $adminPanel->getLoginRouteAction())
            ->name('login');
    });
}
