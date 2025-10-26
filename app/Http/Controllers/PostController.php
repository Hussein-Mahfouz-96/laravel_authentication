<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class PostController extends Controller implements HasMiddleware
{
    use AuthorizesRequests;

    /**
     * Define middleware for the controller
     * Auth required for all methods except index and show (public viewing)
     * 
     * Middleware Explanation:
     * - auth:sanctum: Uses Laravel Sanctum for token-based authentication
     * - except: Excludes specific methods from middleware (allows public access)
     * - Methods like store, update, destroy require valid authentication token
     */
    public static function middleware(): array
    {
        return [
            new Middleware('auth:sanctum', except: ['index', 'show'])
        ];
    }

    /**
     * Get all posts with user info (public access, role-based visibility)
     * 
     * N+1 Query Prevention:
     * - Without eager loading: 1 query for posts + N queries for each post's user = N+1 queries
     * - With eager loading: 1 query for posts + 1 query for all users = 2 queries total
     * - Example: 100 posts = 2 queries instead of 101 queries
     * - Uses 'user:id,name' to only select necessary user fields for performance
     * 
     * Performance Impact:
     * - Constant 2 queries regardless of post count
     * - Reduced memory usage by selecting only required user fields
     * - Faster response times and better scalability
     * 
     * Authentication Context:
     * - $request->user() returns authenticated user object or null if not authenticated
     * - Allows public access but provides different data based on user role
     * - Role checking uses methods like isAdmin(), isEditor() from User model
     * 
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        // All users can view posts, but the list depends on their role
        $user = $request->user();

        if (!$user) {
            // Public access - show all posts
            // with() performs eager loading to prevent N+1 query problem
            $posts = Post::with('user:id,name')->get();
        } elseif ($user->isAdmin() || $user->isEditor()) {
            // Admin and Editor can see all posts
            // Same query but context shows this is for privileged users
            $posts = Post::with('user:id,name')->get();
        } else {
            // Viewers and regular users can see all posts (but this could be restricted)
            // Future enhancement: could filter posts based on user role
            $posts = Post::with('user:id,name')->get();
        }

        return response()->json([
            'posts' => $posts,
            'message' => 'Posts retrieved successfully'
        ]);
    }

    /**
     * Create a new post (authenticated users only, uses policy authorization)
     * 
     * Authorization Flow:
     * - $this->authorize('create', Post::class) checks PostPolicy::create() method
     * - Policy determines if current user can create posts based on business rules
     * - Throws 403 Forbidden if authorization fails
     * 
     * Validation Process:
     * - $request->validate() validates incoming data against defined rules
     * - Returns validated data or throws 422 Unprocessable Entity if validation fails
     * - 'required' ensures field must be present and not empty
     * - 'string' ensures field contains string data
     * - 'max:255' limits field to maximum 255 characters
     * 
     * Relationship Creation:
     * - $request->user()->posts()->create() creates post through relationship
     * - Automatically sets user_id to current authenticated user
     * - load() performs lazy eager loading after creation
     * 
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        // Authorize using policy - checks if user can create posts
        $this->authorize('create', Post::class);

        // Validate incoming request data
        $fields = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string|max:1000'
        ]);

        // Create post through user relationship (automatically sets user_id)
        $post = $request->user()->posts()->create($fields);

        // load() performs lazy eager loading - loads user relationship after creation
        // This prevents additional query when returning the post with user data
        $post->load('user:id,name');

        return response()->json([
            'post' => $post,
            'message' => 'Post created successfully'
        ], 201); // 201 Created status for successful resource creation
    }

    /**
     * Get single post details (public access, policy authorization for authenticated users)
     * 
     * Route Model Binding:
     * - Laravel automatically resolves Post $post parameter from route {post} placeholder
     * - Performs SELECT * FROM posts WHERE id = ? automatically
     * - Throws 404 if post not found
     * 
     * Conditional Authorization:
     * - Only runs authorization if user is authenticated ($request->user() not null)
     * - Allows public viewing while still enforcing policies for authenticated users
     * - Policy might restrict viewing of draft posts or private content
     * 
     * Lazy Eager Loading:
     * - load() method loads relationships after the model is already retrieved
     * - Different from with() which loads relationships during initial query
     * - Useful when you conditionally need relationships or loading after model creation
     * 
     * Display the specified resource.
     */
    public function show(Request $request, Post $post): JsonResponse
    {
        // Authorize using policy only if user is authenticated
        // Allows public access while enforcing business rules for authenticated users
        if ($request->user()) {
            $this->authorize('view', $post);
        }

        // load() performs lazy eager loading - loads user relationship after post retrieval
        // This is different from with() which loads during initial query
        $post->load('user:id,name');

        return response()->json([
            'post' => $post,
            'message' => 'Post retrieved successfully'
        ]);
    }

    /**
     * Update existing post (owner/admin only, uses policy authorization)
     * 
     * Policy Authorization:
     * - authorize('update', $post) passes the specific post instance to policy
     * - Policy can check if current user owns the post or has admin privileges
     * - More granular than role-based checks as it considers post ownership
     * 
     * Partial Updates:
     * - 'sometimes' validation rule means field is only validated if present in request
     * - Allows partial updates - client can send only fields they want to change
     * - Missing fields won't trigger validation errors
     * 
     * Model Updates:
     * - update() method only changes fields provided in $fields array
     * - Automatically updates 'updated_at' timestamp
     * - Returns boolean indicating success/failure
     * 
     * Update the specified resource in storage.
     */
    public function update(Request $request, Post $post): JsonResponse
    {
        // Authorize using policy - checks ownership or admin privileges
        $this->authorize('update', $post);

        // Validate with 'sometimes' rules for partial updates
        $fields = $request->validate([
            'title' => 'sometimes|string|max:255', // Only validate if field is present
            'body' => 'sometimes|string|max:1000'  // Allows partial updates
        ]);

        // Update only the provided fields, automatically updates updated_at timestamp
        $post->update($fields);

        // Reload user relationship for response
        $post->load('user:id,name');

        return response()->json([
            'post' => $post,
            'message' => 'Post updated successfully'
        ]);
    }

    /**
     * Delete a post (owner/admin only, uses policy authorization)
     * 
     * Authorization Security:
     * - Ensures only post owner or admin can delete posts
     * - Policy prevents users from deleting other users' posts
     * 
     * Data Preservation:
     * - Stores $postTitle before deletion for user feedback
     * - After delete(), the model instance becomes unusable
     * - Good UX practice to confirm what was deleted
     * 
     * Soft vs Hard Delete:
     * - delete() performs hard delete (permanent removal)
     * - Could use soft deletes with SoftDeletes trait for recoverability
     * 
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Post $post): JsonResponse
    {
        // Authorize using policy - checks ownership or admin privileges
        $this->authorize('delete', $post);

        // Store title before deletion for response message
        $postTitle = $post->title;

        // Permanently delete the post from database
        $post->delete();

        return response()->json([
            'message' => "Post '{$postTitle}' has been deleted successfully"
        ]); // 200 OK with confirmation message
    }

    /**
     * Get current user's posts only (authenticated user's own posts)
     * 
     * Relationship Queries:
     * - $request->user()->posts() accesses the hasMany relationship
     * - Automatically adds WHERE user_id = ? condition
     * - More efficient than Post::where('user_id', $user->id)
     * 
     * Query Ordering:
     * - latest() orders by created_at DESC (newest first)
     * - Equivalent to orderBy('created_at', 'desc')
     * - Could also use oldest() for ascending order
     * 
     * No User Relationship:
     * - Doesn't load user relationship since we already know it's current user
     * - Saves unnecessary database query and memory
     * - Client can display current user's name from authentication context
     * 
     * Get posts for the current authenticated user
     */
    public function myPosts(Request $request): JsonResponse
    {
        // Query through relationship - automatically filters by current user
        // latest() orders by created_at DESC (newest posts first)
        $posts = $request->user()->posts()->latest()->get();

        return response()->json([
            'posts' => $posts,
            'message' => 'Your posts retrieved successfully'
        ]);
    }

    /**
     * Get posts by specific user ID (all authenticated users can access)
     * 
     * Manual Filtering:
     * - where('user_id', $userId) manually filters posts by specific user
     * - Different from relationship query as we're querying for another user
     * - Could also use User::find($userId)->posts() but this is more efficient
     * 
     * Query Chaining:
     * - Demonstrates Laravel's fluent query builder
     * - where() -> with() -> latest() -> get() chains multiple query modifications
     * - Each method returns query builder instance for chaining
     * 
     * Access Control:
     * - No role restrictions - all authenticated users can view others' posts
     * - Business decision: posts are considered public within authenticated users
     * - Could add privacy controls in future iterations
     * 
     * Get posts by a specific user (All authenticated users can access)
     */
    public function userPosts(Request $request, int $userId): JsonResponse
    {
        // All authenticated users can view other users' posts
        // No role restriction needed - all users can see posts

        // Manual query with eager loading and ordering
        $posts = Post::where('user_id', $userId)  // Filter by specific user
            ->with('user:id,name')                // Eager load user info
            ->latest()                            // Order by newest first
            ->get();                              // Execute query

        return response()->json([
            'posts' => $posts,
            'message' => "User's posts retrieved successfully"
        ]);
    }
}
