<?php

namespace App\Policies;

use App\Models\Purchases;
use App\Models\User;

class PurchasesPolicy
{
    /**
     * Determine whether the user can view any purchases.
     */
    public function viewAny(User $user): bool
    {
        // Tanto admins como empleados pueden ver todas las compras
        return in_array($user->role, ['owner', 'employee']);
    }

    /**
     * Determine whether the user can view the purchase.
     */
    public function view(User $user, Purchases $purchase): bool
    {
        // Tanto admins como empleados pueden ver cualquier compra
        return in_array($user->role, ['owner', 'employee']);
    }

    /**
     * Determine whether the user can create purchases.
     */
    public function create(User $user): bool
    {
        // Tanto admins como empleados pueden crear compras
        return in_array($user->role, ['owner', 'employee']);
    }

    /**
     * Determine whether the user can update the purchase.
     */
    public function update(User $user, Purchases $purchase): bool
    {
        // Los admins pueden actualizar cualquier compra
        if ($user->role === 'owner') {
            return true;
        }

        // Los empleados solo pueden actualizar compras que ellos recibieron
        // y solo si fueron creadas en los últimos 7 días
        if ($user->role === 'employee') {
            $daysSinceCreation = now()->diffInDays($purchase->created_at);
            return $purchase->received_by === $user->id && $daysSinceCreation <= 7;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the purchase.
     */
    public function delete(User $user, Purchases $purchase): bool
    {
        // Solo los admins pueden eliminar compras
        if ($user->role === 'owner') {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can restore the purchase.
     */
    public function restore(User $user, Purchases $purchase): bool
    {
        // Solo los admins pueden restaurar compras
        return $user->role === 'owner';
    }

    /**
     * Determine whether the user can permanently delete the purchase.
     */
    public function forceDelete(User $user, Purchases $purchase): bool
    {
        // Solo los admins pueden eliminar permanentemente
        return $user->role === 'owner';
    }

    /**
     * Determine whether the user can view purchase statistics.
     */
    public function viewStats(User $user): bool
    {
        // Tanto admins como empleados pueden ver estadísticas
        return in_array($user->role, ['owner', 'employee']);
    }

    /**
     * Determine whether the user can view purchases from a specific supplier.
     */
    public function viewBySupplier(User $user): bool
    {
        // Tanto admins como empleados pueden filtrar por proveedor
        return in_array($user->role, ['owner', 'employee']);
    }

    /**
     * Determine whether the user can export purchase data.
     */
    public function export(User $user): bool
    {
        // Solo los admins pueden exportar datos
        return $user->role === 'owner';
    }
}
