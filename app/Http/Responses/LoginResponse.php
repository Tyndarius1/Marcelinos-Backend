<?php

namespace App\Http\Responses;

use Filament\Auth\Http\Responses\Contracts\LoginResponse as LoginResponseContract;
use App\Filament\Pages\AdminDashboard;
use Filament\Pages\Dashboard;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): RedirectResponse|Redirector
    {
        $user = $request->user();

        $role = strtolower(trim((string) ($user?->role ?? '')));

        if ($role === 'admin') {
            return redirect()->to(AdminDashboard::getUrl(panel: 'admin'));
        }

        return redirect()->to(AdminDashboard::getUrl(panel: 'staff'));
    }
}
