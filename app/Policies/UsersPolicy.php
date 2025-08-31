<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

class UsersPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Only owners can view all users
        return $user->isOwner();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $targetUser): bool
    {
        // Users can view their own profile or owners can view any profile
        return $user->id === $targetUser->id || $user->isOwner();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Only owners can create new users
        return $user->isOwner();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $targetUser): bool
    {
        // Users can update their own profile or owners can update any profile
        return $user->id === $targetUser->id || $user->isOwner();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $targetUser): bool
    {
        // Only owners can delete users, and they can't delete themselves
        return $user->isOwner() && $user->id !== $targetUser->id && !$targetUser->isOwner();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, User $targetUser): bool
    {
        return $user->isOwner();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, User $targetUser): bool
    {
        return $user->isOwner() && $user->id !== $targetUser->id && !$targetUser->isOwner();
    }

    /**
     * Determine whether the user can toggle active status.
     */
    public function toggleStatus(User $user, User $targetUser): bool
    {
        // Only owners can toggle status, but they can't deactivate themselves or other owners
        return $user->isOwner() && $user->id !== $targetUser->id && !$targetUser->isOwner();
    }
}
