<?php

use App\Filament\Pages\AdminDashboard;
use App\Http\Controllers\Reports\GuestDemographicsExportController;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Response;

// this is for qr code images for bookings, which are stored in public disk under qr/bookings. 
// We serve them through a route to add CORS headers so they can be fetched from the client app.
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
    ]);
});

// Redirect root to appropriate dashboard based on user role.
// to avoid loop, we check auth()->check() first and redirect to login if not authenticated, so the dashboard redirects don't run for unauthenticated users.
Route::get('/', function () {
    if (! auth()->check()) {
        return redirect('/login');
    }

    $role = strtolower(trim((string) (auth()->user()?->role ?? '')));

    if ($role === 'admin') {
        return redirect(AdminDashboard::getUrl(panel: 'admin'));
    }

    return redirect(AdminDashboard::getUrl(panel: 'staff'));
});


// Signed link from testimonial email: redirects to client app testimonial form.
Route::get('/testimonial/feedback/{token}', function (string $token) {
    $base = rtrim(config('app.frontend_url'), '/');

    return redirect($base.'/testimonial?token='.urlencode($token));
})->where('token', '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}')
    ->name('testimonial.feedback.redirect')
    ->middleware('signed');

if ($adminPanel = Filament::getPanel('admin')) {
    $loginMiddleware = array_merge($adminPanel->getMiddleware(), ['guest']);

    Route::middleware($loginMiddleware)->group(function () use ($adminPanel): void {
        Route::get('/login', $adminPanel->getLoginRouteAction())
            ->name('login');
    });
}

Route::middleware(['web', 'auth'])->group(function (): void {
    Route::get('/reports/guest-demographics/pdf', [GuestDemographicsExportController::class, 'pdf'])
        ->name('reports.guest-demographics.pdf');
});
