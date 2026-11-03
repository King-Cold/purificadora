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
        // La regla de custodia de fijos aplica incluso para Super Admin en cancelaciones
        if ($ability === 'cancel') {
            return null;
        }

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
     * Administrador: permitido para pedidos Pendientes, Confirmados o En ruta sin atención física.
     * Cliente: permitido si el pedido es de su propiedad y no ha comenzado la atención física.
     * Repartidor y Operador Planta: sin acceso a cancelación ordinaria.
     */
    public function cancel(User $user, Order $order): bool
    {
        if ($user->hasRole('Repartidor') || $user->hasRole('Operador Planta')) {
            return false;
        }

        // El estado cancelado o entregado es terminal
        if ($order->isCancelled() || $order->isDelivered()) {
            return false;
        }

        // Si ya comenzó la atención física o se recogieron botellones fijos, se bloquea la cancelación ordinaria para todos
        if ($order->hasCollectedFixedCustody() || $order->hasStartedPhysicalAttention()) {
            return false;
        }

        // Si el usuario es Administrador o Super Admin
        if ($user->hasRole(['Administrador', 'Super Admin']) || $user->can('orders.cancel')) {
            return in_array($order->status, [Order::STATUS_PENDING, Order::STATUS_CONFIRMED, Order::STATUS_IN_ROUTE], true);
        }

        // Si el usuario es Cliente: no puede cancelar si fue confirmado por administración, pero sí en pendiente o en ruta antes de atención física
        if ($user->hasRole('Cliente')) {
            $customer = $user->customer;
            $isOwner = $customer && (int) $order->customer_id === (int) $customer->id;

            return $isOwner && in_array($order->status, [Order::STATUS_PENDING, Order::STATUS_IN_ROUTE], true);
        }

        return false;
    }
}
