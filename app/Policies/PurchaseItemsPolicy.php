<?php

namespace App\Policies;

use App\Models\PurchaseItems;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PurchaseItemsPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->role === 'admin' || $user->role === 'manager' || $user->role === 'owner';
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, PurchaseItems $purchaseItems): bool
    {
        return $user->role === 'admin' || 
               $user->role === 'manager' ||
               $user->role === 'owner' ||
               ($user->role === 'employee' && $purchaseItems->purchase && $purchaseItems->purchase->business_id === $user->business_id);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->role === 'admin' || $user->role === 'manager' || $user->role === 'owner';
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, PurchaseItems $purchaseItems): bool
    {
        return $user->role === 'admin' || 
               $user->role === 'owner' ||
               ($user->role === 'manager' && $purchaseItems->purchase && $purchaseItems->purchase->business_id === $user->business_id);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, PurchaseItems $purchaseItems): bool
    {
        return $user->role === 'admin' || 
               $user->role === 'owner' ||
               ($user->role === 'manager' && $purchaseItems->purchase && $purchaseItems->purchase->business_id === $user->business_id);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, PurchaseItems $purchaseItems): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, PurchaseItems $purchaseItems): bool
    {
        return $user->role === 'admin';
    }
}
