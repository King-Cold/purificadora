<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    /**
     * Tratamiento técnico existente para Super Admin: acceso total antes de cualquier evaluación.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        return null;
    }

    /**
     * Determina si el usuario puede consultar el listado global de pedidos.
     * Administrador: consulta global.
     * Repartidor y Operador Planta: sin acceso en este bloque.
     */
    public function viewAny(User $user): bool
    {
        if ($user->hasRole('Repartidor') || $user->hasRole('Operador Planta')) {
            return false;
        }

        return $user->hasRole('Administrador') || $user->can('orders.view');
    }

    /**
     * Determina si el usuario puede consultar un pedido específico.
     * Administrador: cualquier pedido.
     * Cliente: únicamente pedidos propios vinculados a su Customer.
     * Repartidor y Operador Planta: sin acceso en este bloque.
     */
    public function view(User $user, Order $order): bool
    {
        if ($user->hasRole('Repartidor') || $user->hasRole('Operador Planta')) {
            return false;
        }

        if ($user->hasRole('Administrador') || $user->can('orders.view') && !$user->hasRole('Cliente')) {
            return true;
        }

        if ($user->hasRole('Cliente')) {
            $customer = $user->customer;
            return $customer && (int) $order->customer_id === (int) $customer->id;
        }

        return false;
    }

    /**
     * Determina si el usuario puede registrar un nuevo pedido.
     * Administrador: puede registrar pedidos para clientes.
     * Cliente: puede registrar pedidos exclusivamente para su propio Customer vinculado.
     * Repartidor y Operador Planta: sin acceso en este bloque.
     */
    public function create(User $user): bool
    {
        if ($user->hasRole('Repartidor') || $user->hasRole('Operador Planta')) {
            return false;
        }

        if ($user->hasRole('Administrador') || $user->can('orders.create') && !$user->hasRole('Cliente')) {
            return true;
        }

        if ($user->hasRole('Cliente')) {
            return $user->customer !== null;
        }

        return false;
    }

    /**
     * Determina si el usuario puede confirmar un pedido.
     * Administrador: permitido únicamente si el pedido está en estado Pendiente.
     * Cliente: no puede confirmar pedidos bajo ninguna circunstancia.
     * Repartidor y Operador Planta: sin acceso en este bloque.
     */
    public function confirm(User $user, Order $order): bool
    {
        if ($user->hasRole('Repartidor') || $user->hasRole('Operador Planta') || $user->hasRole('Cliente')) {
            return false;
        }

        if (!$order->canBeConfirmed()) {
            return false;
        }

        return $user->hasRole('Administrador') || $user->can('orders.confirm') || $user->can('orders.update');
    }

    /**
     * Determina si el usuario puede cancelar un pedido.
     * Administrador: permitido tanto para pedidos Pendientes como Confirmados.
     * Cliente: permitido ÚNICAMENTE si el pedido está Pendiente Y es de su propiedad.
     * Repartidor y Operador Planta: sin acceso en este bloque.
     */
    public function cancel(User $user, Order $order): bool
    {
        if ($user->hasRole('Repartidor') || $user->hasRole('Operador Planta')) {
            return false;
        }

        // El estado cancelado es terminal
        if ($order->isCancelled()) {
            return false;
        }

        // Si el usuario es Administrador
        if ($user->hasRole('Administrador') || $user->can('orders.cancel')) {
            return $order->isPending() || $order->isConfirmed();
        }

        // Si el usuario es Cliente: solo puede cancelar sus pedidos mientras estén en estado Pendiente
        if ($user->hasRole('Cliente')) {
            $customer = $user->customer;
            $isOwner = $customer && (int) $order->customer_id === (int) $customer->id;

            return $isOwner && $order->isPending();
        }

        return false;
    }
}
