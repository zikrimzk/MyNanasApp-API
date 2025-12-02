<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PostController extends Controller
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

    public function getPosts(Request $request)
    {
        // Implementation for retrieving posts
        $request->validate([
            'post_type' => 'required|in:All,Announcement,Community',
            'specific_user' => 'nullable|boolean',
        ]);
        
        try {
            if ($request->specific_user) {
                $posts = Post::where('entID', auth()->user()->entID)
                    ->where('post_status', 1)
                    ->with('user')
                    ->get();
                return $this->sendResponse($posts, 'Posts retrieved successfully', true, 200);
            } else {
                $posts = Post::where('post_status', 1)
                ->when($request->post_type !== 'All', function ($query) use ($request) {
                    return $query->where('post_type', $request->post_type);
                })
                ->with('user')
                ->get();
            }
            
            return $this->sendResponse($posts, 'Posts retrieved successfully', true, 200);
        } catch (Exception $e) {
            return $this->sendResponse(null, 'Failed to retrieve posts', false, 500);
        }
    }

    public function addPost(Request $request)
    {
        // 1. Validate 'post_images' as an array of files (max 4, max 10MB each example)
        $request->validate([
            'post_images' => 'array|max:4',
            'post_images.*' => 'image|mimes:jpeg,png,jpg,gif|max:10240', // Each image max 10MB
            'post_caption' => 'required|string',
            'post_location' => 'nullable|string',
            'post_type' => 'required|in:Announcement,Community', // Adjusted to match your Android Chip text usually, or convert in Android
        ]);

        try {
            $user = auth()->user();
            
            // 2. Handle Image Uploads
            $imagePaths = [];
            if ($request->hasFile('post_images')) {
                foreach ($request->file('post_images') as $image) {
                    // Store in 'public/posts' directory
                    $path = $image->store('posts', 'public'); 
                    // Add the path to our array
                    $imagePaths[] = $path;
                }
            }

            // 3. Convert Array of paths to JSON String
            // Example result: ["posts/img1.jpg", "posts/img2.jpg"]
            $jsonImages = !empty($imagePaths) ? json_encode($imagePaths) : null;

            $post = Post::create([
                'post_images' => $jsonImages, // Saving as JSON String
                'post_caption' => $request->post_caption,
                'post_location' => $request->post_location,
                'post_type' => $request->post_type,
                'entID' => $user->entID,
                'post_status' => 1,
                'post_views_count' => 0,
                'post_likes_count' => 0,
            ]);

            return $this->sendResponse($post, 'Post created successfully', true, 201);

        } catch (Exception $e) {
            return $this->sendResponse(null, 'Failed to create post: ' . $e->getMessage(), false, 500);
        }
    }

    public function updatePost(Request $request)
    {
        $request->validate([
            'postID' => 'required|exists:posts,postID',
            'post_caption' => 'nullable|string',
            'post_location' => 'nullable|string',
            'is_delete' => 'required|boolean', // true for delete, false for update
        ]);

        try {
            $user = auth()->user();
            
            $post = Post::find($request->postID);

            if($post->entID !== $user->entID) {
                return $this->sendResponse(null, 'Unauthorized action on this post', false, 403);
            }

            if ($request->is_delete) {
                $post->post_status = 0;
                $post->updated_at = now();
                $post->save();
                return $this->sendResponse(null, 'Post deleted successfully', true, 200);
            } else {
                // if ($request->has('post_caption')) {
                //     $post->post_caption = $request->post_caption;
                // }
                // if ($request->has('post_location')) {
                //     $post->post_location = $request->post_location;
                // }
                $post->post_caption = $request->post_caption ?? $post->post_caption;
                $post->post_location = $request->post_location;
                $post->updated_at = now();
                $post->save();
                return $this->sendResponse($post, 'Post updated successfully', true, 200);
            }
        } catch (Exception $e) {
            return $this->sendResponse(null, 'Failed to update/delete post', false, 500);
        }
    }
}
