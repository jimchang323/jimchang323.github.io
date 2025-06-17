<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Subcategory;
use App\Models\Category;
use Illuminate\Support\Facades\Log;

class SubcategoryController extends Controller
{
    public function create()
    {
        $categories = Category::all();
        return view('Subcategory.create', compact('categories'));
    }

    public function store(Request $request)
    {
        try {
            Log::info('收到子類別新增請求', ['data' => $request->all()]);

            // 驗證輸入，修正表名為 'Category'
            $request->validate([
                'cat_id' => 'required|exists:Category,Cat_id',
                'sub_chinese_name' => 'required|string|max:255',
                'sub_english_name' => 'required|string|max:255',
                'status' => 'required|in:0,1',
            ]);

            // 檢查是否有相同的資料（同一 Cat_id 下）
            $existingSubcategory = Subcategory::where('Cat_id', $request->cat_id)
                ->where(function ($query) use ($request) {
                    $query->where('Sub_CName', $request->sub_chinese_name)
                          ->orWhere('Sub_EName', $request->sub_english_name);
                })->first();

            if ($existingSubcategory) {
                return response()->json(['success' => false, 'message' => '子類別名稱已存在，請重新輸入']);
            }

            // 儲存新子類別
            Subcategory::create([
                'Cat_id' => $request->cat_id,
                'Sub_CName' => $request->sub_chinese_name,
                'Sub_EName' => $request->sub_english_name,
                'S_Status_stop' => $request->status,
            ]);

            return response()->json(['success' => true, 'message' => '子類別已新增']);

        } catch (\Exception $e) {
            Log::error('子類別新增失敗', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'request_data' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => '發生錯誤，請稍後再試: ' . $e->getMessage()
            ], 500);
        }
    }

    public function details(Request $request)
    {
        $catId = $request->query('cat_id');
        if ($catId) {
            $subcategoryes = Subcategory::where('Cat_id', $catId)->paginate(10);
            $category = Category::find($catId);
            return view('Subcategory.details', compact('subcategoryes', 'category'));
        } else {
            $subcategoryes = Subcategory::paginate(10);
            return view('Subcategory.details', compact('subcategoryes'));
        }
    }

    public function updateStatus(Request $request)
    {
        try {
            Log::info('收到狀態更新請求', ['data' => $request->all()]);

            $request->validate([
                'id' => 'required|integer',
                'status' => 'required|in:0,1',
            ]);

            $Subcategory = Subcategory::find($request->id);

            if (!$Subcategory) {
                return response()->json(['success' => false, 'message' => '找不到該氣體資料'], 404);
            }

            $Subcategory->S_Status_stop = $request->status;
            $Subcategory->save();

            Log::info('狀態更新成功', ['id' => $request->id, 'status' => $request->status]);

            return response()->json(['success' => true, 'message' => '狀態更新成功']);
        } catch (\Exception $e) {
            Log::error('狀態更新失敗', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => '狀態更新失敗，請稍後再試'], 500);
        }
    }
}