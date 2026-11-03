<?php

namespace App\Policies;

use App\Models\DeliveryRoute;
use App\Models\User;

class DeliveryRoutePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasRole('Administrador') || $user->hasRole('Repartidor') || $user->can('routes.view');
    }

    public function view(User $user, DeliveryRoute $route): bool
    {
        if ($user->hasRole('Administrador') || $user->can('routes.view')) {
            return true;
        }

        if ($user->hasRole('Repartidor')) {
            return (int) $route->driver_user_id === (int) $user->id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('Administrador') || $user->can('routes.assign');
    }

    public function deliver(User $user, DeliveryRoute $route): bool
    {
        if ($user->hasRole('Administrador')) {
            return true;
        }

        if ($user->hasRole('Repartidor') || $user->can('routes.deliver')) {
            return (int) $route->driver_user_id === (int) $user->id;
        }

        return false;
    }
}

