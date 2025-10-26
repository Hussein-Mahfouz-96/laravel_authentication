<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Role;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Check if user has a specific role
     */
    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    /**
     * Check if user is admin
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    /**
     * Check if user is editor
     */
    public function isEditor(): bool
    {
        return $this->hasRole('editor');
    }

    /**
     * Check if user is viewer
     */
    public function isViewer(): bool
    {
        return $this->hasRole('viewer');
    }

    /**
     * Check if user has permission to perform an action
     */
    public function canPerform(string $action, $model = null): bool
    {
        switch ($this->role) {
            case 'admin':
                return true; // Admin can do everything
            case 'editor':
                // Editor can read users and manage posts
                if ($model instanceof User) {
                    return $action === 'read'; // Editor can read users but not manage them
                }
                return in_array($action, ['create', 'read', 'update', 'delete']);
            case 'viewer':
                // Viewer can read users and manage own posts
                if ($model instanceof User) {
                    return $action === 'read'; // Viewer can read users
                }
                return $action === 'read'; // Viewer can only read posts (own posts managed in controller)
            default:
                return false; // Regular users (newly registered) have no special permissions
        }
    }

    /**
     * Get posts belonging to this user
     */
    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    /**
     * Check if user can create posts
     */
    public function canCreatePosts(): bool
    {
        return in_array($this->role, ['admin', 'editor', 'viewer']);
    }

    /**
     * Check if user can view a specific post
     */
    public function canViewPost(Post $post): bool
    {
        return true; // All authenticated users can view posts
    }

    /**
     * Check if user can update a specific post
     */
    public function canUpdatePost(Post $post): bool
    {
        // Admin and Editor can update any post
        if (in_array($this->role, ['admin', 'editor'])) {
            return true;
        }

        // Users can update their own posts
        return $post->belongsToUser($this);
    }

    /**
     * Check if user can delete a specific post
     */
    public function canDeletePost(Post $post): bool
    {
        // Admin can delete any post
        if ($this->isAdmin()) {
            return true;
        }

        // Editor cannot delete others' posts, only their own
        if ($this->isEditor()) {
            return $post->belongsToUser($this);
        }

        // Users can delete their own posts
        return $post->belongsToUser($this);
    }
}
