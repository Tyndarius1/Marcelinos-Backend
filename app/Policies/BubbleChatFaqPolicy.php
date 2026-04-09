<?php

namespace App\Policies;

use App\Models\BubbleChatFaq;
use App\Models\User;

class BubbleChatFaqPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPrivilege('manage_bubble_chat_faqs');
    }

    public function view(User $user, BubbleChatFaq $bubbleChatFaq): bool
    {
        return $user->hasPrivilege('manage_bubble_chat_faqs');
    }

    public function create(User $user): bool
    {
        return $user->hasPrivilege('manage_bubble_chat_faqs');
    }

    public function update(User $user, BubbleChatFaq $bubbleChatFaq): bool
    {
        return $user->hasPrivilege('manage_bubble_chat_faqs');
    }

    public function delete(User $user, BubbleChatFaq $bubbleChatFaq): bool
    {
        return $user->hasPrivilege('manage_bubble_chat_faqs');
    }

    public function restore(User $user, BubbleChatFaq $bubbleChatFaq): bool
    {
        return $user->hasPrivilege('manage_bubble_chat_faqs');
    }

    public function forceDelete(User $user, BubbleChatFaq $bubbleChatFaq): bool
    {
        return strtolower(trim((string) ($user->role ?? ''))) === 'admin';
    }
}

