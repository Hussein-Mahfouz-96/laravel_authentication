<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        // 1- name
        // 2- email
        // 3- password
        // 4- password confirmation

        $fields = $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8|confirmed',
            'role' => 'sometimes|in:admin,editor,viewer' // Role is optional
        ]);

        // Default role for new registrations is 'viewer'
        $fields['role'] = $fields['role'] ?? 'viewer';

        $user = User::create($fields);

        return response()->json([
            'user' => $user,
            'message' => 'User has been created !'
        ]);
    }

    public function login(Request $request)
    {
        // 1- email
        // 2- password
        // 3- verify in case user exists and passwords are the same 
        // 4- create an access token

        $request->validate([
            'email' => 'required|email|exists:users',
            'password' => 'required'
        ]);

        $user = User::where('email', $request->email)->first();


        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                "message" => "Please enter valid credentials"
            ]);
        };

        // Set token expiration to 7 days
        $tokenResult = $user->createToken($request->email, ["*"], now()->addDays(7));
        $token = $tokenResult->plainTextToken;

        return response()->json([
            "user" => $user,
            "token" => $token,
            "message" => "You are logged in !"
        ], 200);
    }

    public function logout(Request $request)
    {

        $request->user()->tokens()->delete();

        return response()->json([
            'message' => 'You Are Logged Out'
        ]);
    }
}
