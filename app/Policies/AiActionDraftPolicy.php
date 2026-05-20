<?php

namespace App\Policies;

use App\Models\AiActionDraft;
use App\Models\User;

class AiActionDraftPolicy
{
    public function confirm(User $user, AiActionDraft $draft): bool
    {
        return $draft->user_id === $user->id;
    }

    public function cancel(User $user, AiActionDraft $draft): bool
    {
        return $draft->user_id === $user->id;
    }
}
