<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Order $order): bool
    {
        return $user->isAdmin() || $order->customer?->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Only admins move orders through the fulfilment state machine.
     */
    public function updateStatus(User $user, Order $order): bool
    {
        return $user->isAdmin();
    }

    public function cancel(User $user, Order $order): bool
    {
        return $this->view($user, $order);
    }
}
