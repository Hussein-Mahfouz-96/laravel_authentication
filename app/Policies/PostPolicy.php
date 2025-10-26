<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PostPolicy
{
    /**
     * Determine if the user can view any posts
     */
    public function viewAny(User $user): bool
    {
        // All authenticated users can view posts
        return true;
    }

    /**
     * Determine if the user can view the post
     */
    public function view(User $user, Post $post): bool
    {
        // All authenticated users can view any post
        return true;
    }

    /**
     * Determine if the user can create posts
     */
    public function create(User $user): bool
    {
        // All authenticated users can create posts
        return true;
    }

    /**
     * Determine if the user can update the post
     */
    public function update(User $user, Post $post): Response
    {
        // Admin can update any post
        if ($user->isAdmin()) {
            return Response::allow();
        }

        // Editor can update any post
        if ($user->isEditor()) {
            return Response::allow();
        }

        // Users can update their own posts
        return $user->id === $post->user_id
            ? Response::allow()
            : Response::deny("You can only update your own posts");
    }

    /**
     * Determine if the user can delete the post
     */
    public function delete(User $user, Post $post): Response
    {
        // Admin can delete any post
        if ($user->isAdmin()) {
            return Response::allow();
        }

        // Editor can only delete their own posts
        if ($user->isEditor()) {
            return $user->id === $post->user_id
                ? Response::allow()
                : Response::deny("Editors can only delete their own posts");
        }

        // Users can delete their own posts
        return $user->id === $post->user_id
            ? Response::allow()
            : Response::deny("You can only delete your own posts");
    }
}
