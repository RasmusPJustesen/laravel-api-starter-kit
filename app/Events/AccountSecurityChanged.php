<?php

namespace App\Events;

use App\Models\User;

final readonly class AccountSecurityChanged
{
    public function __construct(public User $user, public string $action, public ?string $recipient = null) {}
}
