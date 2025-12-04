<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\PremiseController;
use App\Http\Controllers\ProductController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::get('/', function (Request $request) {
    return response()->json([
        'message' => 'Server is up and running',
    ], 200);
});

// Auth routes - no authentication required
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    // Auth routes
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/change_password', [AuthController::class, 'changePassword']);
    
    // User routes
    Route::post('/get_users', [UserController::class, 'getUsers']);
    Route::post('/update_user_profile', [UserController::class, 'updateUserProfile']);

    // Post routes
    Route::post('/get_posts', [PostController::class, 'getPosts']);
    Route::post('/add_post', [PostController::class, 'addPost']);
    Route::post('/update_post', [PostController::class, 'updatePost']);
    Route::post('/like_post', [PostController::class, 'likePost']);
    Route::post('/view_post', [PostController::class, 'viewPost']);

    // Premise routes
    Route::post('/get_premises', [PremiseController::class, 'getPremises']);
    Route::post('/add_premise', [PremiseController::class, 'addPremise']);
    Route::post('/update_premise', [PremiseController::class, 'updatePremise']);

    // Product routes
    Route::post('/get_product_categories', [ProductController::class, 'getProductCategories']);
    Route::post('/get_products', [ProductController::class, 'getProducts']);
    Route::post('/add_product', [ProductController::class, 'addProduct']);
    Route::post('/update_product', [ProductController::class, 'updateProduct']);
});

// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });
