<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Fuel;

class FuelController extends Controller
{
    // 處理表單提交並回傳 JSON
    public function store(Request $request)
    {
        $request->validate([
            'F_CName' => 'required|string|max:50',
            'F_EName' => 'nullable|string|max:50',
            'F_Status_stop' => 'nullable|boolean',
        ]);
        
        // 檢查是否有重複的排放源名稱
           $existingCategory = Fuel::where('F_CName', $request->input('F_CName'))->first();

           if ($existingCategory) {
              return response()->json([
              'success' => false,
              'message' => '排放源名稱重複！'
           ]);
           }


        try {
            // 建立新的排放源
            $category = Fuel::create([
                'F_CName' => $request->input('F_CName'),
                'F_EName' => $request->input('F_EName', ''), 
                'F_Status_stop' => $request->input('F_Status_stop', 1)
            ]);

            return response()->json([
                'success' => true,
                'message' => '排放源名稱已成功新增！',
                'category' => $category
            ]);
           } 
           catch (\Exception $e)
           {
             return response()->json([
                'success' => false,
                'message' => '資料新增失敗：' . $e->getMessage()
             ], 500);
            }
    }


    // 切換啟用/停用狀態
    public function toggle(Request $request, $F_ID)
    {
        try {
            // 使用傳遞的 $F_ID 找到對應的排放源類別
            $category = Fuel::findOrFail($F_ID);
    
            // 從請求中獲取新的狀態值
            $newStatus = $request->input('F_Status_stop');
    
            // 確保狀態值有效
            if (!in_array($newStatus, [0, 1])) {
                return response()->json([
                    'success' => false,
                    'message' => '無效的狀態值。',
                ], 400);
            }
    
            // 更新狀態
            $category->F_Status_stop = $newStatus;
            $category->save();
    
            return response()->json([
                'success' => true,
                'newStatus' => $category->F_Status_stop,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '更新失敗：' . $e->getMessage()
            ], 500);
        }
    }

    // 顯示所有排放源
    public function create()
    {
        $categories = Fuel::all();
        return view('fuel.create', compact('categories'));
    }

    // 顯示所有排放源類別的詳細資料
    public function details()
    {
        $categories = Fuel::paginate(5); // 每頁顯示 5 筆資料
        return view('fuel.details', compact('categories'));
    }
}