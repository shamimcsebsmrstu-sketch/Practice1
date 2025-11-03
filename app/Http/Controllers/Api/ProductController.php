<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    // GET /api/product
    public function index()
    {
        return response()->json(Product::latest()->get());
    }

    // POST /api/product
    public function store(Request $request)
    {
        // Conditional validation: allow file upload, base64 data URL, or external URL
        $rules = [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price'=> 'nullable|numeric',
        ];

        if ($request->hasFile('image')) {
            $rules['image'] = 'image|max:5120'; // validate uploaded file
        } else {
            $rules['image'] = 'nullable|string'; // accept base64 string or URL
        }

        $validated = $request->validate($rules);

        // Handle image input
        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('products', 'public');
        } elseif ($request->filled('image')) {
            $imageInput = (string) $request->input('image');

            // data URL base64: data:image/{ext};base64,{data}
            if (preg_match('/^data:image\/(\w+);base64,/', $imageInput, $type)) {
                $data = substr($imageInput, strpos($imageInput, ',') + 1);
                $data = base64_decode($data);
                if ($data !== false) {
                    $extension = strtolower($type[1]);
                    $fileName = 'products/'.uniqid('img_', true).'.'.$extension;
                    Storage::disk('public')->put($fileName, $data);
                    $validated['image'] = $fileName;
                }
            } elseif (filter_var($imageInput, FILTER_VALIDATE_URL)) {
                // If a URL is provided, store the URL as-is (no download)
                $validated['image'] = $imageInput;
            }
        }

        $product = Product::create($validated);

        return response()->json($product, 201);
    }

    // GET /api/product/{id}
    public function show($id)
    {
        $product = Product::findOrFail($id);
        return response()->json($product);
    }

    // PUT/PATCH /api/product/{id}
    public function update(Request $request, Product $product)
    {
        // Conditional validation for update
        $rules = [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ];
        if ($request->hasFile('image')) {
            $rules['image'] = 'image|max:5120';
        } else {
            $rules['image'] = 'nullable|string';
        }

        $validated = $request->validate($rules);

        // Process image if provided
        if ($request->hasFile('image')) {
            // delete old image if exists and was stored locally
            if ($product->image && !filter_var($product->image, FILTER_VALIDATE_URL) && Storage::disk('public')->exists($product->image)) {
                Storage::disk('public')->delete($product->image);
            }
            $validated['image'] = $request->file('image')->store('products', 'public');
        } elseif ($request->filled('image')) {
            $imageInput = (string) $request->input('image');

            if (preg_match('/^data:image\/(\w+);base64,/', $imageInput, $type)) {
                // delete old local image if exists
                if ($product->image && !filter_var($product->image, FILTER_VALIDATE_URL) && Storage::disk('public')->exists($product->image)) {
                    Storage::disk('public')->delete($product->image);
                }
                $data = substr($imageInput, strpos($imageInput, ',') + 1);
                $data = base64_decode($data);
                if ($data !== false) {
                    $extension = strtolower($type[1]);
                    $fileName = 'products/'.uniqid('img_', true).'.'.$extension;
                    Storage::disk('public')->put($fileName, $data);
                    $validated['image'] = $fileName;
                }
            } elseif (filter_var($imageInput, FILTER_VALIDATE_URL)) {
                // For URL, just store URL string
                $validated['image'] = $imageInput;
            }
        }

        $product->update($validated);

        return response()->json($product);
    }

    // DELETE /api/product/{id}
    public function destroy($id)
    {
        $product = Product::findOrFail($id);
        if ($product->image && !filter_var($product->image, FILTER_VALIDATE_URL) && Storage::disk('public')->exists($product->image)) {
            Storage::disk('public')->delete($product->image);
        }
        $product->delete();

        return response()->json(['message' => 'Product deleted successfully']);
    }
}
