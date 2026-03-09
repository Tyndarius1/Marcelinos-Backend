<?php

namespace App\Http\Middleware;

use App\Filament\Pages\AdminDashboard;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $role = strtolower(trim((string) (auth()->user()?->role ?? '')));

        if ($role !== 'admin') {
            return redirect()->to(AdminDashboard::getUrl(panel: 'staff'));
        }

        return $next($request);
    }
}
