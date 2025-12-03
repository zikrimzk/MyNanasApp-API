<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Post;
use App\Models\User;
use App\Models\Premise;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class UserController extends Controller
{
    private function sendResponse($data, $message, $status = true, $code = 200)
    {
        return response()->json([
            'status' => $status,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    public function getUsers(Request $request)
    {
        $request->validate([
            'specific_user' => 'nullable|boolean',
            'entID' => 'required_if:specific_user,true|exists:users,entID',
        ]);

        try {
            $users = null;
            if ($request->specific_user) {
                $users = User::where('entID', $request->entID)->where('ent_account_status', 1)->first();

                // 1. Total Active Posts
                $users->total_posts = Post::where('entID', $users->entID)
                    ->where('post_status', 1) // Only count active posts
                    ->count();

                // 2. Total Active Products (Assuming you have a Product model and status)
                // Step A: Get all premiseIDs belonging to this user
                $premiseIDs = Premise::where('entID', $users->entID)
                    ->where('premise_status', 1) // Optional: Ensure the premise itself is active
                    ->pluck('premiseID'); // Returns an array like [1, 5, 8]

                // Step B: Count products where the premiseID is in that array
                $users->total_products = Product::whereIn('premiseID', $premiseIDs)
                    ->where('product_status', 1)
                    ->count();

                // 3. Total Likes (Total likes RECEIVED on their posts)
                // This sums up the 'post_likes_count' column of all posts owned by this user
                $users->total_likes = Post::where('entID', $users->entID)
                    ->where('post_status', 1)
                    ->sum('post_likes_count');
            } else {
                $users = User::where('entID', '!=', auth()->user()->entID)->where('ent_account_status', 1)->get();
            }

            if (!$users) {
                return $this->sendResponse(null, 'User not found', false, 404);
            }

            return $this->sendResponse($users, 'User profile retrieved successfully');

        } catch (Exception $e) {
            return $this->sendResponse(null, 'Failed to retrieve user profile', false, 500);
        }
    }  

    public function updateUserProfile(Request $request)
    {
        $request->validate([
            'ent_fullname' => 'sometimes|required|string',
            'ent_username' => 'sometimes|required|string|unique:users,ent_username,' . auth()->user()->entID . ',entID',
            'ent_bio' => 'sometimes|nullable|string',
            'ent_profilePhoto' => 'sometimes|nullable|image|mimes:jpeg,png,jpg,gif|max:10240',
        ]);

        try {
            $user = auth()->user();

            // 2. Handle Image Uploads
            if ($request->hasFile('ent_profilePhoto')) {
                $image = $request->file('ent_profilePhoto');
                // Store in 'public/profile_photos' directory
                $path = $image->store('profile_photos', 'public'); 
                // Set the path to user's profile photo
                $user->ent_profilePhoto = $path;
                
            }

            if ($request->has('ent_fullname')) {
                $user->ent_fullname = $request->ent_fullname;
            }
            if ($request->has('ent_username')) {
                $user->ent_username = $request->ent_username;
            }
            if ($request->has('ent_bio')) {
                $user->ent_bio = $request->ent_bio;
            }

            $user->save();

            return $this->sendResponse($user, 'User profile updated successfully');

        } catch (Exception $e) {
            return $this->sendResponse(null, 'Failed to update user profile', false, 500);
        }
    }
}
