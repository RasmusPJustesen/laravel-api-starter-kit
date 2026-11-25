<?php

namespace App\Events;

use App\Models\User;

final readonly class DeviceSessionsRevoked
{
    public function __construct(public User $user, public string $scope, public ?string $session = null) {}
}
