<?php

namespace App\Policies;

use App\Models\Categories;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CategoriesPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Todos los usuarios autenticados pueden ver las categorías
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Categories $category): bool
    {
        // Todos los usuarios autenticados pueden ver una categoría específica
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Solo propietarios pueden crear categorías
        return $user->isOwner();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Categories $category): bool
    {
        // Solo propietarios pueden actualizar categorías
        return $user->isOwner();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Categories $category): bool
    {
        // Solo propietarios pueden eliminar categorías
        return $user->isOwner();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Categories $category): bool
    {
        return $user->isOwner();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Categories $category): bool
    {
        return $user->isOwner();
    }
}
