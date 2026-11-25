<?php

namespace App\Support\Auth;

use App\Events\AccountSecurityChanged;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class SecurityEvents
{
    public static function dispatch(User $user, string $action, ?string $recipient = null): void
    {
        DB::afterCommit(fn () => event(new AccountSecurityChanged($user, $action, $recipient)));
    }
}
