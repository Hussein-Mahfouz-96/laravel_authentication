<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes Test Case Matrix
|--------------------------------------------------------------------------
| 
| AUTHENTICATION ROUTES:
| POST /api/register        | Public  | Register new user
| POST /api/login           | Public  | Login with credentials
| POST /api/logout          | Auth    | Logout current user
|
| USER PROFILE ROUTES:
| GET  /api/profile         | Auth    | Get current user profile
|
| POST MANAGEMENT ROUTES:
| GET    /api/posts         | Public  | List all posts (with user info)
| POST   /api/posts         | Auth    | Create new post (policy check)
| GET    /api/posts/{id}    | Public  | Show single post (with user info)
| PUT    /api/posts/{id}    | Auth    | Update post (owner/admin only)
| PATCH  /api/posts/{id}    | Auth    | Partial update post (owner/admin only)
| DELETE /api/posts/{id}    | Auth    | Delete post (owner/admin only)
| GET    /api/my-posts      | Auth    | Get current user's posts
| GET    /api/users/{id}/posts | Auth | Get specific user's posts
|
| USER MANAGEMENT ROUTES (View Access):
| GET  /api/users           | Admin/Editor/Viewer | List all users
| GET  /api/users/{id}      | Admin/Editor/Viewer | Show single user
| GET  /api/users-with-posts| Admin/Editor/Viewer | Users with their posts
|
| USER MANAGEMENT ROUTES (Admin Only):
| POST   /api/users         | Admin   | Create new user
| DELETE /api/users/{id}    | Admin   | Delete user (not self)
| POST   /api/users/{id}/promote | Admin | Change user role
|
| USER PROFILE MANAGEMENT:
| PUT    /api/users/{id}    | Self/Admin | Update user profile
| PATCH  /api/users/{id}    | Self/Admin | Partial update user profile
|
| ROLE PERMISSIONS:
| - Admin: Full access to all routes
| - Editor: Can view users, manage own posts, view all posts
| - Viewer: Can view users, manage own posts, view all posts
| - Regular User: Can manage own posts, view all posts, update own profile
| - Public: Can register, login, view posts
|
*/

/**
 * Authentication Routes (Public Access)
 * 
 * Route Security:
 * - No middleware protection for public registration and login
 * - logout requires auth:sanctum to ensure valid token exists before logout
 * 
 * HTTP Methods:
 * - POST used for all auth routes (standard REST practice for state-changing operations)
 * - POST /register: Creates new user account
 * - POST /login: Authenticates user and returns token
 * - POST /logout: Invalidates current authentication token
 * 
 * Controller Array Syntax:
 * - [AuthController::class, 'register'] resolves to controller method
 * - Uses class constant for better IDE support and refactoring safety
 */

// Public authentication endpoints
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Protected logout endpoint - requires valid authentication token
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

/**
 * Protected Routes Group (Requires Authentication)
 * 
 * Middleware Group:
 * - middleware('auth:sanctum') applies Sanctum token authentication to all nested routes
 * - group() creates a route group with shared middleware
 * - All routes inside require valid Bearer token in Authorization header
 * 
 * Route Organization:
 * - Groups related functionality together
 * - Prevents repetitive middleware declarations
 * - Easier maintenance and security management
 */
Route::middleware('auth:sanctum')->group(function () {

    /**
     * User Profile Route (All Authenticated Users)
     * 
     * Access Level: All authenticated users can access their own profile
     * HTTP Method: GET (read-only operation)
     * Returns: Current user's profile information
     * 
     * Test Cases:
     * ✓ Valid token returns user profile
     * ✗ No token returns 401 Unauthorized
     * ✗ Invalid token returns 401 Unauthorized
     * ✗ Expired token returns 401 Unauthorized
     */
    Route::get('/profile', [UserController::class, 'profile']);

    /**
     * Post Management Routes (All Authenticated Users)
     * 
     * apiResource() generates RESTful routes:
     * - GET    /posts       -> index()   (public via controller middleware)
     * - POST   /posts       -> store()   (auth required)
     * - GET    /posts/{id}  -> show()    (public via controller middleware)
     * - PUT    /posts/{id}  -> update()  (auth required)
     * - PATCH  /posts/{id}  -> update()  (auth required)
     * - DELETE /posts/{id}  -> destroy() (auth required)
     * 
     * Controller Middleware Override:
     * - PostController has except: ['index', 'show'] for public access
     * - This allows public viewing while requiring auth for modifications
     * 
     * Policy Authorization:
     * - Uses PostPolicy for granular permissions (owner/admin checks)
     * - More secure than role-based checks for resource ownership
     */
    Route::apiResource('/posts', PostController::class);

    /**
     * Additional Post Routes (All Authenticated Users)
     * 
     * Custom Routes Beyond Standard REST:
     * - /my-posts: Returns current user's posts only
     * - /users/{userId}/posts: Returns specific user's posts
     * 
     * Route Parameters:
     * - {userId} is captured as integer parameter in controller method
     * - Laravel automatically validates and passes to userPosts() method
     * 
     * Test Cases for /my-posts:
     * ✓ Authenticated user gets own posts
     * ✗ Unauthenticated user gets 401
     * ✓ Returns empty array if user has no posts
     * ✓ Posts ordered by latest first
     * 
     * Test Cases for /users/{userId}/posts:
     * ✓ Any authenticated user can view others' posts
     * ✓ Valid userId returns user's posts
     * ✓ Invalid userId returns empty array
     * ✗ Unauthenticated user gets 401
     */
    Route::get('/my-posts', [PostController::class, 'myPosts']);
    Route::get('/users/{userId}/posts', [PostController::class, 'userPosts']); // All users can see others' posts

    /**
     * User Management Routes - View Access (Admin, Editor, Viewer)
     * 
     * Nested Middleware Group:
     * - Additional auth:sanctum middleware (redundant but explicit)
     * - Could be simplified since already in auth group
     * 
     * Role-Based Access Control:
     * - Controller methods check user role (admin, editor, viewer)
     * - Returns 403 Forbidden for insufficient permissions
     * 
     * Route Model Binding:
     * - {user} parameter automatically resolves to User model instance
     * - Throws 404 if user not found
     * 
     * Test Cases for /users:
     * ✓ Admin can view all users
     * ✓ Editor can view all users
     * ✓ Viewer can view all users
     * ✗ Regular user gets 403 Forbidden
     * ✗ Unauthenticated user gets 401
     * 
     * Test Cases for /users/{user}:
     * ✓ Admin/Editor/Viewer can view specific user
     * ✓ Returns 404 for non-existent user
     * ✗ Regular user gets 403 Forbidden
     * 
     * Test Cases for /users-with-posts:
     * ✓ Admin/Editor/Viewer get users with their recent posts
     * ✓ Eager loading prevents N+1 queries
     * ✗ Regular user gets 403 Forbidden
     */
        Route::get('/users', [UserController::class, 'index']); // Admin, Editor, Viewer
        Route::get('/users/{user}', [UserController::class, 'show']); // Admin, Editor, Viewer
        Route::get('/users-with-posts', [UserController::class, 'usersWithPosts']); // Admin, Editor, Viewer

    /**
     * Admin-Only User Management Routes
     * 
     * Custom Middleware:
     * - role:admin middleware restricts access to admin users only
     * - This middleware needs to be implemented in app/Http/Middleware
     * - Should check if $request->user()->isAdmin()
     * 
     * High-Privilege Operations:
     * - User creation: Only admins can create new users
     * - User deletion: Only admins can delete users
     * - Role promotion: Only admins can change user roles
     * 
     * Security Considerations:
     * - Self-deletion prevention (implemented in controller)
     * - Self-role-change prevention (implemented in controller)
     * - Admin creation restrictions (only admins can create other admins)
     * 
     * Test Cases for POST /users:
     * ✓ Admin can create new user with any role
     * ✓ Validates required fields (name, email, password, role)
     * ✓ Prevents duplicate email addresses
     * ✗ Editor/Viewer/Regular user gets 403
     * ✗ Invalid data returns 422 validation errors
     * 
     * Test Cases for DELETE /users/{user}:
     * ✓ Admin can delete other users
     * ✗ Admin cannot delete themselves
     * ✗ Non-admin gets 403 Forbidden
     * ✓ Returns 404 for non-existent user
     * 
     * Test Cases for POST /users/{user}/promote:
     * ✓ Admin can change user roles (admin/editor/viewer)
     * ✗ Admin cannot change own role
     * ✗ Invalid role returns 422 validation error
     * ✗ Non-admin gets 403 Forbidden
     */
        Route::post('/users', [UserController::class, 'store']); // Admin only
        Route::delete('/users/{user}', [UserController::class, 'destroy']); // Admin only
        Route::post('/users/{user}/promote', [UserController::class, 'promote']); // Admin only - promote/demote

    /**
     * User Profile Management Routes (Self + Admin Access)
     * 
     * HTTP Method Flexibility:
     * - PUT: Complete resource replacement (all fields)
     * - PATCH: Partial resource update (only provided fields)
     * - Both routes point to same controller method
     * 
     * Access Control Logic:
     * - Users can update their own profile
     * - Admins can update any user's profile
     * - Implemented in controller, not middleware
     * 
     * Route Duplication:
     * - PUT and PATCH both map to update() method
     * - Controller uses 'sometimes' validation for PATCH flexibility
     * - Allows clients to choose semantic HTTP method
     * 
     * Nested Middleware (Redundant):
     * - auth:sanctum middleware already applied at parent level
     * - This group is unnecessary but doesn't harm functionality
     * 
     * Test Cases for PUT/PATCH /users/{user}:
     * ✓ User can update own profile (name, email, password)
     * ✓ Admin can update any user's profile
     * ✓ Admin can change user roles
     * ✗ User cannot update other users' profiles
     * ✗ User cannot change own role
     * ✗ Non-existent user returns 404
     * ✓ Email uniqueness validation (except current user)
     * ✓ Password hashing on update
     * ✗ Invalid data returns 422 validation errors
     */
        Route::put('/users/{user}', [UserController::class, 'update']); // Self + Admin
        Route::patch('/users/{user}', [UserController::class, 'update']); // Self + Admin
});
