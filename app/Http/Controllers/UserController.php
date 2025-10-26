<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Display a listing of users (Admin and Editor and Viewer only)
     */
    public function index(Request $request): JsonResponse
    {
        // Admin, Editor, and Viewer can list all users
        if (!in_array($request->user()->role, ['admin', 'editor', 'viewer'])) {
            return response()->json(['message' => 'Forbidden: Insufficient permissions to view users'], 403);
        }

        $users = User::select('id', 'name', 'email', 'role', 'created_at')->get();

        return response()->json([
            'users' => $users,
            'message' => 'Users retrieved successfully'
        ]);
    }

    /**
     * Store a newly created user (Admin and Editor only)
     */
    public function store(Request $request): JsonResponse
    {
        // Only admin can create users
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Forbidden: Only admins can create users'], 403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'role' => ['required', Rule::in(['admin', 'editor', 'viewer'])],
        ]);

        // Only admins can create other admins
        if ($request->role === 'admin' && !$request->user()->isAdmin()) {
            return response()->json(['message' => 'Only admins can create admin users'], 403);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
        ]);

        return response()->json([
            'user' => $user->only(['id', 'name', 'email', 'role']),
            'message' => 'User created successfully'
        ], 201);
    }

    /**
     * Display the specified user
     */
    public function show(Request $request, User $user): JsonResponse
    {
        // Admin, Editor, and Viewer can view specific users
        if (!in_array($request->user()->role, ['admin', 'editor', 'viewer'])) {
            return response()->json(['message' => 'Forbidden: Insufficient permissions to view user details'], 403);
        }

        return response()->json([
            'user' => $user->only(['id', 'name', 'email', 'role', 'created_at']),
            'message' => 'User retrieved successfully'
        ]);
    }

    /**
     * Update the specified user (Admin and Editor only, with restrictions)
     */
    public function update(Request $request, User $user): JsonResponse
    {
        // Only admin can update users (including promote/demote)
        if (!$request->user()->isAdmin()) {
            // Users can update their own profile (except role)
            if ($user->id !== $request->user()->id) {
                return response()->json(['message' => 'You can only update your own profile'], status: 403);
            }
        }

        $validationRules = [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:users,email,' . $user->id,
            'password' => 'sometimes|string|min:8',
        ];

        // Only admin can change roles (promote/demote)
        if ($request->user()->isAdmin()) {
            $validationRules['role'] = ['sometimes', Rule::in(['admin', 'editor', 'viewer'])];
        }

        $request->validate($validationRules);

        // Prevent users from changing their own role
        if ($user->id === $request->user()->id && $request->has('role')) {
            return response()->json(['message' => 'You cannot change your own role'], 403);
        }

        $updateData = $request->only(['name', 'email']);

        if ($request->has('password')) {
            $updateData['password'] = Hash::make($request->password);
        }

        // Only admin can update roles
        if ($request->user()->isAdmin() && $request->has('role')) {
            $updateData['role'] = $request->role;
        }

        $user->update($updateData);

        return response()->json([
            'user' => $user->only(['id', 'name', 'email', 'role']),
            'message' => 'User updated successfully'
        ]);
    }
    /**
     * Remove the specified user (Admin only)
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        // Only admin can delete users
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Forbidden: Only admins can delete users'], 403);
        }

        // Prevent admins from deleting themselves
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'You cannot delete your own account'], 403);
        }

        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully'
        ]);
    }

    /**
     * Get current user profile
     */
    public function profile(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $request->user()->only(['id', 'name', 'email', 'role', 'created_at']),
            'message' => 'Profile retrieved successfully'
        ]);
    }

    /**
     * Promote user to a higher role (Admin only)
     */
    public function promote(Request $request, User $user): JsonResponse
    {
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Forbidden: Only admins can promote users'], 403);
        }

        $request->validate([
            'role' => ['required', Rule::in(['admin', 'editor', 'viewer'])]
        ]);

        // Prevent admin from changing their own role
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'You cannot change your own role'], 403);
        }

        $oldRole = $user->role;
        $user->update(['role' => $request->role]);

        return response()->json([
            'user' => $user->only(['id', 'name', 'email', 'role']),
            'message' => "User role changed from {$oldRole} to {$request->role}"
        ]);
    }

    /**
     * Get users with their posts (Admin, Editor, Viewer only)
     */
    public function usersWithPosts(Request $request): JsonResponse
    {
        // Admin, Editor, and Viewer can see all users with posts
        if (!in_array($request->user()->role, ['admin', 'editor', 'viewer'])) {
            return response()->json(['message' => 'Forbidden: Insufficient permissions'], 403);
        }

        $users = User::with(['posts' => function ($query) {
            $query->select('id', 'title', 'body', 'user_id', 'created_at')
                ->latest()
                ->limit(5); // Limit to latest 5 posts per user
        }])->select('id', 'name', 'email', 'role', 'created_at')->get();

        return response()->json([
            'users' => $users,
            'message' => 'Users with posts retrieved successfully'
        ]);
    }
}
