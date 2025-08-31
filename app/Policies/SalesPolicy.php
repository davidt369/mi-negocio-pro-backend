<?php

namespace App\Policies;

use App\Models\Sales;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SalesPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Owners can view all sales, employees can view sales they made
        return $user->role === 'owner' || $user->role === 'employee';
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Sales $sale): bool
    {
        // Owners can view any sale, employees can only view their own sales
        return $user->role === 'owner' || $sale->sold_by === $user->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Both owners and employees can create sales
        return $user->role === 'owner' || $user->role === 'employee';
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Sales $sale): bool
    {
        // Only owners can update sales, or employees can update their own sales on the same day
        if ($user->role === 'owner') {
            return true;
        }
        
        // Employees can only update their own sales from the same day
        return $sale->sold_by === $user->id && 
               $sale->sale_date === today()->toDateString();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Sales $sale): bool
    {
        // Only owners can delete sales
        return $user->role === 'owner';
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Sales $sale): bool
    {
        // Only owners can restore sales
        return $user->role === 'owner';
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Sales $sale): bool
    {
        // Only owners can permanently delete sales
        return $user->role === 'owner';
    }
}
