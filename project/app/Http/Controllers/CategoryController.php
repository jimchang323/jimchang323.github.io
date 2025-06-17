<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Category;
use Illuminate\Support\Facades\Log;

class CategoryController extends Controller
{
    public function create()
    {
        return view('Category.create');
    }

    public function store(Request $request)
    {
        try {
            Log::info('收到類別新增請求', ['data' => $request->all()]);

            // 驗證輸入
            $request->validate([
                'chinese_name' => 'required',
                'english_name' => 'required',
                'category' => 'required',     // 新增類別驗證
                'ecategory' => 'required',    // 新增英文類別驗證
                'status' => 'required|in:0,1', // 驗證 status 欄位
            ]);

            // 檢查是否有相同的資料
            $existingGas = Category::where('Cat_CName', $request->chinese_name)
                ->orWhere('Cat_EName', $request->english_name)
                ->first();

            if ($existingGas) {
                return response()->json(['success' => false, 'message' => '類別名稱已存在，請重新輸入']);
            }

            // 儲存新類別
            Category::create([
                'Cat_CName' => $request->chinese_name,
                'Cat_EName' => $request->english_name,
                'Cat_Category' => $request->category,     // 新增存入類別
                'Cat_Ecategory' => $request->ecategory,  // 新增存入英文類別
                'C_Status_stop' => $request->status,     // 使用表單傳來的狀態值
            ]);

            return response()->json(['success' => true, 'message' => '類別已新增']);

        } catch (\Exception $e) {
            Log::error('類別新增失敗', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return response()->json([
                'success' => false,
                'message' => '發生錯誤，請稍後再試'
            ], 500);
        }
    }

    public function details(Request $request)
    {
        // 使用 paginate 方法，每頁顯示 5 筆資料
        $categories = Category::paginate(5);
        return view('Category.details', compact('categories'));
    }

    public function updateStatus(Request $request)
    {
        try {
            Log::info('收到狀態更新請求', ['data' => $request->all()]);
    
            // 確保請求包含 `id` 和 `status`
            $request->validate([
                'id' => 'required|integer',
                'status' => 'required|in:0,1',
            ]);
    
            // 獲取資料
            $category = Category::find($request->id);
    
            if (!$category) {
                return response()->json(['success' => false, 'message' => '找不到該類別資料'], 404);
            }
    
            // 更新狀態
            $category->C_Status_stop = $request->status;
            $category->save();
    
            Log::info('狀態更新成功', ['id' => $request->id, 'status' => $request->status]);
    
            return response()->json(['success' => true, 'message' => '狀態更新成功']);
    
        } catch (\Exception $e) {
            Log::error('狀態更新失敗', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => '狀態更新失敗，請稍後再試'], 500);
        }
    }
}