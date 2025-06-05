<?php

namespace App\Http\Controllers\V1\RetailerApp;

use App\Helpers\Helper;
use App\Models\SizeVariant;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Product;

class ProductController extends Controller
{
    public function fetchProducts(Request $request)
    {
        $query = Product::with(['sizeVariants', 'productImages','colorVariants']);
        if ($request->has('category')) {
            $query->where('category_id', $request->input('category'));
        }

        if ($request->has('mode_type')) {
            $query->where('mode_type', $request->input('mode_type'));
        }

        if ($request->has('size')) {
            $query->whereHas('sizeVariants', function($q) use ($request) {
                $q->where('size_name', $request->input('size'));
            });
        }
        $products = $query->get();

        return response()->json([
            'status_code' => 1,
            'data' => ['products' => $products],
            'message' => 'Categories fetched successfully.'
        ]);
    }
    public function fetchSizes(Request $request)
    {
        // $request->validate([
        //     'category' => 'required|exists:categories,id  ',
        // ]);
        $sizes = SizeVariant::select('size_variants.size_name')
        ->join('products','products.id','size_variants.product_id')
        ->where('products.category_id',$request->category)
        ->distinct()
        ->get();

        return response()->json([
            'status_code' => 1,
            'data' => ['sizes' => $sizes],
            'message' => 'Categories fetched successfully.'
        ]);
    }
}
