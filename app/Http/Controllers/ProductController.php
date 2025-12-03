<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Product;
use Illuminate\Http\Request;
use App\Models\ProductCategory;
use App\Http\Controllers\Controller;

class ProductController extends Controller
{
    private function sendResponse($data, $message, $status = true, $code = 200)
    {
        return response()->json([
            'status' => $status,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    public function getProductCategories(Request $request)
    {
        try {
            $productCategories = ProductCategory::all();
            return $this->sendResponse($productCategories, 'Product categories retrieved successfully');
        } catch (Exception $e) {
            return $this->sendResponse(null, $e->getMessage(), false, 500);
        }
    }

    public function getProducts(Request $request)
    {
        $request->validate([
            'premise_state' => 'required|string', // All or specific state
            'premise_city' => 'required|string', // All or specific city
            // 'categoryID' => 'required|exists:product_categories,categoryID', // All or specific category
            'specific_user' => 'nullable|boolean', // true for specific user, false for all
            'productID' => 'nullable|exists:products,productID', // true for specific product
        ]);

        try {
            $user = auth()->user(); // Get current logged in user

            // Start the query
            $query = Product::with('premise', 'category');

            // Apply Filters
            if ($request->specific_user) {
                $query->whereHas('premise', function ($q) use ($user) {
                    $q->where('entID', $user->entID);
                });
            } else if ($request->productID) {
                $query->where('productID', $request->productID);
            } else {
                $query->where('product_status', 1);
                if ($request->premise_state !== 'All') {
                    $query->where('premise_state', $request->premise_state);
                }
                if ($request->premise_city !== 'All') {
                    $query->where('premise_city', $request->premise_city);
                }
            }

            // Get the results
            $products = $query->get();

            return $this->sendResponse($products, 'Products retrieved successfully');
        } catch (Exception $e) {
            return $this->sendResponse(null, $e->getMessage(), false, 500);
        }
    }

    public function addProduct(Request $request)
    {
        // 1. Validate 'product_image' as an array of files (max 4, max 10MB each example)
        $request->validate([
            'product_image' => 'array|max:4',
            'product_image.*' => 'image|mimes:jpeg,png,jpg,gif|max:10240', // Each image max 10MB
            'product_name' => 'required|string',
            'product_qty' => 'required|numeric',
            'product_unit' => 'required|string',
            'product_price' => 'required|numeric',
            'product_description' => 'nullable|string',
            'categoryID' => 'required|exists:product_categories,categoryID',
            'premiseID' => 'required|exists:premises,premiseID',
        ]);

        try {
            // 2. Handle Image Uploads
            $imagePaths = [];
            if ($request->hasFile('product_image')) {
                foreach ($request->file('product_image') as $image) {
                    // Store in 'public/products' directory
                    $path = $image->store('products', 'public'); 
                    // Add the path to our array
                    $imagePaths[] = $path;
                }
            }

            // 3. Convert Array of paths to JSON String
            // Example result: ["posts/img1.jpg", "posts/img2.jpg"]
            $jsonImages = !empty($imagePaths) ? json_encode($imagePaths) : null;

            $product = Product::create([
                'product_name' => $request->product_name,
                'product_description' => $request->product_description,
                'product_image' => $jsonImages,
                'product_unit' => $request->product_unit,
                'product_qty' => $request->product_qty,
                'product_price' => $request->product_price,
                'categoryID' => $request->categoryID,
                'premiseID' => $request->premiseID,
                'product_status' => 2, // 2 = pending
            ]);

            return $this->sendResponse($product, 'Product added successfully');
        } catch (Exception $e) {
            return $this->sendResponse(null, $e->getMessage(), false, 500);
        }
    }

    public function updateProduct(Request $request)
    {
        // 1. Validate 'product_image' as an array of files (max 4, max 10MB each example)
        $request->validate([
            'is_delete' => 'required|boolean', // true for delete, false for update
            'product_image' => 'array|max:4',
            'product_image.*' => 'image|mimes:jpeg,png,jpg,gif|max:10240', // Each image max 10MB
            'product_category' => 'sometimes|required|exists:product_categories,categoryID',
            'product_name' => 'sometimes|required|string',
            'product_qty' => 'sometimes|required|numeric',
            'product_unit' => 'sometimes|required|string',
            'product_price' => 'sometimes|required|numeric',
            'product_description' => 'sometimes|nullable|string',
            'premiseID' => 'sometimes|required|exists:premises,premiseID',
            'productID' => 'required|exists:products,productID',
        ]);

        try {
            if ($request->is_delete) {
                $product = Product::find($request->productID);
                $product->product_status = 0;
                $product->updated_at = now();
                $product->save();
                return $this->sendResponse(null, 'Product deleted successfully', true, 200);
            }
            // 2. Handle Image Uploads
            $imagePaths = [];
            if ($request->hasFile('product_image')) {
                foreach ($request->file('product_image') as $image) {
                    // Store in 'public/products' directory
                    $path = $image->store('products', 'public'); 
                    // Add the path to our array
                    $imagePaths[] = $path;
                }
            }

            // 3. Convert Array of paths to JSON String
            // Example result: ["posts/img1.jpg", "posts/img2.jpg"]
            $jsonImages = !empty($imagePaths) ? json_encode($imagePaths) : null;

            $product = Product::find($request->productID);
            $product->product_name = $request->product_name;
            $product->product_description = $request->product_description;
            $product->product_image = $jsonImages;
            $product->product_category = $request->product_category;
            $product->product_unit = $request->product_unit;
            $product->product_qty = $request->product_qty;
            $product->product_price = $request->product_price;
            $product->premiseID = $request->premiseID;
            $product->product_status = 2;
            $product->save();

            return $this->sendResponse($product, 'Product updated successfully');
        } catch (Exception $e) {
            return $this->sendResponse(null, $e->getMessage(), false, 500);
        }
    }
}
