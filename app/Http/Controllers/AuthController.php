<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Post;
use App\Models\User;
use App\Models\Premise;
use App\Models\Product;
use App\Models\UserPost;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    // Helper function to keep responses consistent
    private function sendResponse($data, $message, $status = true, $code = 200)
    {
        return response()->json([
            'status' => $status,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

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
            
            return $this->sendResponse($user, 'Registration successful', true, 201);

        } catch (Exception $e) {
            Log::error("Registration failed: " . $e->getMessage());
            return $this->sendResponse(null, 'Registration failed', false, 500);
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
                return $this->sendResponse(null, 'Invalid username or password', false, 401);
            }

            // 1. Total Active Posts
            $user->total_posts = Post::where('entID', $user->entID)
                ->where('post_status', 1) // Only count active posts
                ->count();

            // 2. Total Active Products (Assuming you have a Product model and status)
            // Step A: Get all premiseIDs belonging to this user
            $premiseIDs = Premise::where('entID', $user->entID)
                ->where('premise_status', 1) // Optional: Ensure the premise itself is active
                ->pluck('premiseID'); // Returns an array like [1, 5, 8]

            // Step B: Count products where the premiseID is in that array
            $user->total_products = Product::whereIn('premiseID', $premiseIDs)
                ->where('product_status', 1)
                ->count();

            // 3. Total Likes (Total likes RECEIVED on their posts)
            // This sums up the 'post_likes_count' column of all posts owned by this user
            $user->total_likes = Post::where('entID', $user->entID)
                ->where('post_status', 1)
                ->sum('post_likes_count');

            $token = $user->createToken('mobile_token')->plainTextToken;

            // Combine Token and User into one object for the 'data' field
            $responseData = [
                'token' => $token,
                'user' => $user
            ];

            return $this->sendResponse($responseData, 'Login successful');

        } catch (Exception $e) {
            Log::error("Login Error: " . $e->getMessage());
            Log::error($e->getTraceAsString()); // See which line failed
            return $this->sendResponse(null, 'Login error', false, 500);
        }
    }

    public function logout(Request $request)
    {
        try {
            if ($request->user()) {
                $request->user()->tokens()->delete();
            }
            return $this->sendResponse(null, 'Logged out successfully');

        } catch (Exception $e) {
            return $this->sendResponse(null, 'Failed to logout', false, 500);
        }
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required', // We need this for security
            'new_password' => 'required|min:8',
        ]);

        try {
            $user = auth()->user();

            // 1. Security Check: Does the Current Password match DB?
            if (!Hash::check($request->current_password, $user->ent_password)) {
                return $this->sendResponse(null, 'Current password is incorrect', false, 401);
            }

            // 2. Logic Check: Is the New Password the same as the Old one?
            if (Hash::check($request->new_password, $user->ent_password)) {
                return $this->sendResponse(null, 'New password cannot be the same as the old password', false, 400);
            }

            // 3. Update Password 
            $user->ent_password = Hash::make($request->new_password);
            $user->save();

            // 4. Revoke tokens (Optional: logs user out of all devices)
            $user->tokens()->delete(); 

            return $this->sendResponse(null, 'Password changed successfully', true, 200);

        } catch (Exception $e) {
            return $this->sendResponse(null, 'Failed to change password', false, 500);
        }
    }
}
