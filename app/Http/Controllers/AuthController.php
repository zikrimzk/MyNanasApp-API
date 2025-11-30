<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'ent_fullname' => 'required',
            'ent_icNo' => 'required|unique:users,ent_icNo',
            'ent_dob' => 'required|date',
            'ent_phoneNo' => 'required|unique:users,ent_phoneNo',
            'ent_email' => 'required|email|unique:users,ent_email',
            'ent_username' => 'required|unique:users,ent_username',
            'ent_password' => 'required|min:8',
            'ent_business_name' => 'nullable',
            'ent_business_ssmNo' => 'nullable',
        ]);

        try {
            $user = User::create([
                'ent_fullname' => $request->ent_fullname,
                'ent_icNo' => $request->ent_icNo,
                'ent_dob' => $request->ent_dob,
                'ent_phoneNo' => $request->ent_phoneNo,
                'ent_email' => $request->ent_email,
                'ent_username' => $request->ent_username,
                'ent_password' => Hash::make($request->ent_password),
                'ent_business_name' => $request->ent_business_name, 
                'ent_business_ssmNo' => $request->ent_business_ssmNo,
            ]);

            // $token = $user->createToken('mobile_token')->plainTextToken;

            return response()->json([
                'message' => 'Registration successful',
                // 'token' => $token,
                'user' => $user
            ], 201);

        } catch (Exception $e) {
            Log::error("Registration failed: " . $e->getMessage());

            return response()->json([
                'message' => 'Registration failed due to a server error. Please try again later.',
                'error_code' => 'DB_INSERT_FAILED' 
            ], 500);
        }
    }

    public function login(Request $request)
    {
        $request->validate([
            'ent_username' => 'required',
            'ent_password' => 'required'
        ]);

        try {
            $user = User::where('ent_username', $request->ent_username)->first();

            if (!$user || !Hash::check($request->ent_password, $user->ent_password)) {
                return response()->json([
                    'message' => 'Invalid username or password'
                ], 401);
            }

            $token = $user->createToken('mobile_token')->plainTextToken;

            return response()->json([
                'message' => 'Login successful',
                'token' => $token,
                'user' => $user
            ], 200);

        } catch (Exception $e) {
            Log::error("Login Error: " . $e->getMessage());

            return response()->json([
                'message' => 'An error occurred while logging in. Please try again.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function logout(Request $request)
    {
        try {
            if ($request->user()) {
                $request->user()->tokens()->delete();
            }

            return response()->json([
                'message' => 'Logged out successfully'
            ], 200);

        } catch (Exception $e) {
            Log::error("Logout Error: " . $e->getMessage());

            return response()->json([
                'message' => 'Failed to logout. Please try again.',
            ], 500);
        }
    }
}
