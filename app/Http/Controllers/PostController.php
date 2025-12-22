<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Post;
use App\Models\User;
use App\Models\UserPost;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

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

   public function getNewPosts(Request $request) 
    {
        $user = auth()->user();
        
        // Ensure we use the correct column name from your migration
        $time = $user->ent_last_seen_post??Carbon::now();

        // Must call ->count() to get the integer value
        $count = Post::where('post_verified_at', '>=', $time)
                    ->where('post_status', 1)
                    ->count();

        $verified = Post::where('post_verified_at', '>=', $time)
                        ->where('post_status', 1)
                        ->where('entID', $user->entID)
                        ->count();

        //dd($user, $count, $time, $verified );
        if($count == 0){
            return $this->sendResponse(null, "No Notification", false, 200);
        }

        $message = "There are $count new posts for you.";

        if ($verified > 0) {
            $message .= " You have $verified posts pass verification.";
        }

        // Call using $this-> and pass $message into the 'message' argument
        return $this->sendResponse(null, $message, true, 200);
    }

    public function getPosts(Request $request)
    {
        // Implementation for retrieving posts
        $request->validate([
            'post_type' => 'required|in:All,Announcement,Community',
            'specific_user' => 'nullable|boolean',
            'entID' => 'nullable|exists:users,entID',
        ]);
        
        try {
            if($request->entID) {
                $user = User::where('entID', $request->entID)->first();
            } else {
                $user = auth()->user(); // Get current logged in user
            }

            // Start the query
            $query = Post::where('post_status', 1)
                ->with('user'); // Load the author of the post

            // == KEY CHANGE: Eager Load the user's interaction ==
            // We load the 'userPosts' relationship, but ONLY where entID matches the current user
            // userPosts is the same as "likes"
            $query->with(['userPosts' => function ($q) use ($user) {
                $q->where('entID', $user->entID);
            }]);

            // Apply Filters
            if ($request->specific_user) {
                $query->where('entID', $user->entID);
            } else {
                if ($request->post_type !== 'All') {
                    $query->where('post_type', $request->post_type);
                }
            }

            // Get the results
            $posts = $query->get();

            // == TRANSFORM THE DATA ==
            // The API currently returns a nested array for 'userPosts'. 
            // We need to flatten this into a simple "is_liked": true/false field.
            $posts->transform(function ($post) {
                
                // Check if the 'userPosts' collection has any items (meaning the user interacted)
                // AND check if the specific 'is_liked' column in that row is true (1)
                $interaction = $post->userPosts->first();
                
                $post->is_liked = ($interaction && $interaction->is_liked) ? true : false;
                
                // Remove the 'userPosts' array from the output to keep JSON clean
                unset($post->userPosts); 
                
                return $post;
            });

            auth()->user()->update([
                'ent_last_seen_post' => Carbon::now()
            ]);
            
            return $this->sendResponse($posts, 'Posts retrieved successfully', true, 200);
        } catch (Exception $e) {
            return $this->sendResponse(null, 'Failed to retrieve posts', false, 500);
        }
    }

   public function verifyPost($postId) {
    // 1. Corrected query (use where first, then firstOrFail)
    $post = Post::where('postID', $postId)
                ->where('entID', auth()->user()->entID)
                ->firstOrFail();

    //dd($post);
    // 2. Format images (ensure post_images is cast to array in Model)
    $imageLinks = "";
    if (is_array($post->post_images)) {
        foreach ($post->post_images as $image) {
            $imageLinks .= asset('storage/' . $image) . " | ";
        }
        $imageLinks = rtrim($imageLinks, " | ");
    }

    // 3. Call Mistral API
    $response = Http::withoutVerifying()
    ->withHeaders([
        'X-API-KEY' => config('services.mistral.key'),
        'Content-Type' => 'application/json',
    ])->post('https://api.mistral.ai/v1/conversations', [
        'model' => 'magistral-small-latest',
        'inputs' => [[
            'role' => 'user',
            'content' => "<text>{$post->post_caption}</text><image>{$imageLinks}</image>"
        ]],
        'instructions' => config('services.mistral.instructions'),
        'completion_args' => [
            'temperature' => 0,
            'max_tokens' => 256,
            'top_p' => 1
        ]
    ]);

    if ($response->successful()) {
        $result = $response->json();

        $contentString = $result['outputs'][0]['content'] ?? '{}';
        // 2. Decode the inner string into an array
        $verificationDetails = json_decode($contentString, true);
        //dd($contentString,$verificationDetails);

        if($verificationDetails['dangerous_image'] > 0.3 || $verificationDetails['dangerous_text'] > 0.3){
            $post->update([
                'post_verification' => 'Failed',
                'post_verification_details' => $verificationDetails, 
            ]);
            return $this->sendResponse($verificationDetails, "Verification failed. Please check your content.", true, 200);

        }else{
            
            $res = $post->update([
                'post_verification' => 'Verified',
                'post_verification_details' => $verificationDetails, 
                'post_verified_at' => now(),
            ]);

          
            return $this->sendResponse($verificationDetails, "Post verified successfully", true, 200);

        }
        // 3. Update Post
       

    }

    return $this->sendResponse(null, "Verification failed. Please try again later", false, 500);
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
                'post_verification'=>"Pending"
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

    public function likePost(Request $request)
    {
        $request->validate([
            'postID' => 'required|exists:posts,postID',
            'is_liked' => 'required|boolean',
        ]);

        try {
            $user = auth()->user();
            $post = Post::find($request->postID);

            $userPost = UserPost::firstOrNew([
                'entID' => $user->entID,
                'postID' => $post->postID,
            ]);

            if ($request->is_liked) {
                // Like the post
                if (!$userPost->exists || !$userPost->is_liked) {
                    $userPost->is_liked = true;
                    $userPost->userpost_status = 1;
                    $userPost->save();

                    // Increment likes count
                    $post->post_likes_count += 1;
                    $post->save();
                }
                return $this->sendResponse(null, 'Post liked successfully', true, 200);
            } else {
                // Unlike the post
                if ($userPost->exists && $userPost->is_liked) {
                    $userPost->is_liked = false;
                    $userPost->userpost_status = 1;
                    $userPost->save();

                    // Decrement likes count
                    $post->post_likes_count = max(0, $post->post_likes_count - 1);
                    $post->save();
                }
                return $this->sendResponse(null, 'Post unliked successfully', true, 200);
            }
        } catch (Exception $e) {
            return $this->sendResponse(null, 'Failed to like/unlike post', false, 500);
        }
    }

    public function viewPost(Request $request)
    {
        $request->validate([
            'postID' => 'required|exists:posts,postID',
        ]);

        try {
            $post = Post::find($request->postID);
            
            // Increment the view count
            $post->post_views_count += 1;
            $post->save();

            return $this->sendResponse(null, 'View count incremented', true, 200);
        } catch (Exception $e) {
            return $this->sendResponse(null, 'Failed to increment view', false, 500);
        }
    }
}
