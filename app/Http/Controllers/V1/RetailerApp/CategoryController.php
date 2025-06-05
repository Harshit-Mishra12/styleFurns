<?php
namespace App\Http\Controllers\V1\RetailerApp;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function fetchCategoriesByGender(Request $request)
    {
        $request->validate([
            'gender' => 'required|in:MALE,FEMALE',
        ]);
        $categories = Category::where('gender',$request->gender)->get();

        return response()->json([
            'status_code' => 1,
            'data' => ['categories' => $categories],
            'message' => 'Categories fetched successfully.'
        ]);
    }
}