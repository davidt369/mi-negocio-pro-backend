<?php

namespace App\Policies;

use App\Models\SaleItems;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SaleItemsPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Todos los usuarios autenticados pueden ver la lista de items de venta
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, SaleItems $saleItems): bool
    {
        // Todos los usuarios autenticados pueden ver items de venta específicos
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Todos los usuarios autenticados pueden crear items de venta
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, SaleItems $saleItems): bool
    {
        // Solo el owner puede actualizar items de venta
        // Los empleados pueden actualizar solo si fueron ellos quienes hicieron la venta
        if ($user->role === 'owner') {
            return true;
        }
        
        // Verificar si el empleado fue quien hizo la venta original
        return $saleItems->sale && $saleItems->sale->sold_by === $user->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, SaleItems $saleItems): bool
    {
        // Solo el owner puede eliminar items de venta
        // Los empleados pueden eliminar solo si fueron ellos quienes hicieron la venta
        // y solo el mismo día
        if ($user->role === 'owner') {
            return true;
        }
        
        // Para empleados: solo pueden eliminar items de ventas que hicieron el mismo día
        if ($saleItems->sale && $saleItems->sale->sold_by === $user->id) {
            $saleDate = $saleItems->sale->sale_date;
            $today = now()->format('Y-m-d');
            
            return $saleDate === $today;
        }
        
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, SaleItems $saleItems): bool
    {
        // Solo el owner puede restaurar items de venta eliminados
        return $user->role === 'owner';
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, SaleItems $saleItems): bool
    {
        // Solo el owner puede eliminar permanentemente items de venta
        return $user->role === 'owner';
    }

    /**
     * Determine whether the user can view statistics.
     */
    public function viewStats(User $user): bool
    {
        // Todos los usuarios autenticados pueden ver estadísticas básicas
        return true;
    }

    /**
     * Determine whether the user can manage items for a specific sale.
     */
    public function manageSaleItems(User $user, $saleId): bool
    {
        // Verificar si el usuario puede gestionar items para una venta específica
        $sale = \App\Models\Sales::find($saleId);
        
        if (!$sale) {
            return false;
        }
        
        // El owner puede gestionar cualquier venta
        if ($user->role === 'owner') {
            return true;
        }
        
        // Los empleados solo pueden gestionar ventas que ellos hicieron
        return $sale->sold_by === $user->id;
    }
}
