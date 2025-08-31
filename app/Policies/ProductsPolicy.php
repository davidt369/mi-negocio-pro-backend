<?php

namespace App\Policies;

use App\Models\Products;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ProductsPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Both owner and employee can view products
        return $user->isOwner() || $user->isEmployee();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Products $product): bool
    {
        // Both owner and employee can view products
        return $user->isOwner() || $user->isEmployee();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Only owner can create products
        return $user->isOwner();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Products $product): bool
    {
        // Only owner can update products
        return $user->isOwner();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Products $product): bool
    {
        // Only owner can delete products
        return $user->isOwner();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Products $product): bool
    {
        // Only owner can restore products
        return $user->isOwner();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Products $product): bool
    {
        // Only owner can force delete products
        return $user->isOwner();
    }
}
