<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;

/**
 * Client hanya order miliknya, teknisi hanya order yang ditugaskan kepadanya, admin semua.
 */
class OrderPolicy
{
    public function before(User $user): ?bool
    {
        if (! $user->is_active) {
            return false;
        }

        return $user->hasRole(UserRole::ADMIN) ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Order $order): bool
    {
        return match ($user->role) {
            UserRole::CLIENT => $order->user_id === $user->id,
            UserRole::TEKNISI => $order->technician_id !== null && $order->technician_id === $user->technician?->id,
            UserRole::ADMIN => true,
        };
    }

    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::CLIENT);
    }
}
