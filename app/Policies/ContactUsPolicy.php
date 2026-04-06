<?php

namespace App\Policies;

use App\Models\ContactUs;
use App\Models\User;

class ContactUsPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPrivilege('manage_contact_messages');
    }

    public function view(User $user, ContactUs $contactUs): bool
    {
        return $user->hasPrivilege('manage_contact_messages');
    }

    public function create(User $user): bool
    {
        return $user->hasPrivilege('manage_contact_messages');
    }

    public function update(User $user, ContactUs $contactUs): bool
    {
        return $user->hasPrivilege('manage_contact_messages');
    }

    public function delete(User $user, ContactUs $contactUs): bool
    {
        return $user->hasPrivilege('manage_contact_messages');
    }

    public function restore(User $user, ContactUs $contactUs): bool
    {
        return $user->hasPrivilege('manage_contact_messages');
    }

    public function forceDelete(User $user, ContactUs $contactUs): bool
    {
        return strtolower(trim((string) ($user->role ?? ''))) === 'admin';
    }

    public function bulkDelete(User $user): bool
    {
        return $user->hasPrivilege('manage_contact_messages');
    }
}
