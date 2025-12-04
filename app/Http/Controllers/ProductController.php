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
                $query->where('productID', $request->productID)->where('product_status', '!=', 0);
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
            'product_desc' => 'nullable|string',
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
                'product_desc' => $request->product_desc,
                'product_image' => $jsonImages,
                'product_unit' => $request->product_unit,
                'product_qty' => $request->product_qty,
                'product_price' => $request->product_price,
                'categoryID' => $request->categoryID,
                'premiseID' => $request->premiseID,
                'product_status' => 1,
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
            'productID' => 'required|exists:products,productID',
            'is_delete' => 'required|boolean', // true for delete, false for update
            'categoryID' => 'nullable|exists:product_categories,categoryID',
            'product_name' => 'nullable|string',
            'product_qty' => 'nullable|numeric',
            'product_unit' => 'nullable|string',
            'product_price' => 'nullable|numeric',
            'product_desc' => 'nullable|string',
            'existing_images' => 'nullable|array', // List of paths (strings) user kept
            'new_images' => 'nullable|array', // List of new files
            'new_images.*' => 'image|mimes:jpeg,png,jpg,gif|max:10240', // Each image max 10MB
            'premiseID' => 'nullable|exists:premises,premiseID',
        ]);

        try {
            $product = Product::find($request->productID);

            // === DELETE LOGIC ===
            if ($request->is_delete) {
                $product->updated_at = now();
                $product->product_status = 0; // Soft Delete
                $product->save();
                return $this->sendResponse(null, 'Product deleted successfully', true, 200);
            }

            // === UPDATE LOGIC ===

            // 1. Handle Images
            $currentDbImages = json_decode($product->product_image) ?? [];
            $keptImages = $request->existing_images ?? []; // What user wants to keep
            $newFiles = $request->file('new_images') ?? [];

            // VALIDATION: Min 1, Max 4
            $totalCount = count($keptImages) + count($newFiles);
            if ($totalCount < 1) {
                return $this->sendResponse(null, 'Product must have at least 1 image.', false, 422);
            }
            if ($totalCount > 4) {
                return $this->sendResponse(null, 'Product cannot have more than 4 images.', false, 422);
            }

            // 2. CLEANUP: Delete removed images from physical storage
            // If a file is in DB but NOT in $keptImages, delete it.
            $imagesToDelete = array_diff($currentDbImages, $keptImages);
            foreach ($imagesToDelete as $fileToDelete) {
                if (\Illuminate\Support\Facades\Storage::disk('public')->exists($fileToDelete)) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($fileToDelete);
                }
            }

            // 3. UPLOAD: Add new images
            $finalImageList = $keptImages; // Start with kept ones
            foreach ($newFiles as $image) {
                $path = $image->store('products', 'public');
                $finalImageList[] = $path;
            }

            // 4. Update Database
            $product->product_name = $request->product_name ?? $product->product_name;
            $product->product_desc = $request->product_desc ?? $product->product_desc;
            $product->product_image = json_encode(array_values($finalImageList)); // Re-index array
            $product->categoryID = $request->categoryID ?? $product->categoryID;
            $product->product_unit = $request->product_unit ?? $product->product_unit;
            $product->product_qty = $request->product_qty ?? $product->product_qty;
            $product->product_price = $request->product_price ?? $product->product_price;
            $product->premiseID = $request->premiseID ?? $product->premiseID;
            $product->product_status = $request->product_status ?? $product->product_status; // e.g. set back to pending if changed
            
            $product->updated_at = now();
            $product->save();

            return $this->sendResponse($product, 'Product updated successfully', true, 200);
        } catch (Exception $e) {
            return $this->sendResponse(null, $e->getMessage(), false, 500);
        }
    }
}
