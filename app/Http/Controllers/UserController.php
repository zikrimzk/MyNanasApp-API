<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Exception;

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

    public function getUserProfile(Request $request)
    {
        $request->validate([
            'ent_username' => 'required|string',
        ]);

        try {
            $user = User::where('ent_username', $request->ent_username)->first();

            if (!$user) {
                return $this->sendResponse(null, 'User not found', false, 404);
            }

            return $this->sendResponse($user, 'User profile retrieved successfully');

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
