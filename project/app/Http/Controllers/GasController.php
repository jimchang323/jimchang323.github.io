<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Gas;

class GasController extends Controller
{
    // 處理表單提交並回傳 JSON
    public function store(Request $request)
    {
        $request->validate([
            'G_CName' => 'required|string|max:50',
            'G_EName' => 'nullable|string|max:50',
            'G_Status_stop' => 'nullable|boolean',
        ]);
        
        // 檢查是否有重複的排放源名稱
           $existingCategory = Gas::where('G_CName', $request->input('G_CName'))->first();

           if ($existingCategory) {
              return response()->json([
              'success' => false,
              'message' => '氣體名稱重複！'
           ]);
           }


        try {
            // 建立新的排放源
            $category = Gas::create([
                'G_CName' => $request->input('G_CName'),
                'G_EName' => $request->input('G_EName', ''), 
                'G_Status_stop' => $request->input('G_Status_stop', 1)
            ]);

            return response()->json([
                'success' => true,
                'message' => '氣體名稱已成功新增！',
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
    public function toggle(Request $request, $G_ID)
    {
        try {
            // 使用傳遞的 $F_ID 找到對應的排放源類別
            $category = Gas::findOrFail($G_ID);
    
            // 從請求中獲取新的狀態值
            $newStatus = $request->input('G_Status_stop');
    
            // 確保狀態值有效
            if (!in_array($newStatus, [0, 1])) {
                return response()->json([
                    'success' => false,
                    'message' => '無效的狀態值。',
                ], 400);
            }
    
            // 更新狀態
            $category->G_Status_stop = $newStatus;
            $category->save();
    
            return response()->json([
                'success' => true,
                'newStatus' => $category->G_Status_stop,
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
        $categories = Gas::all();
        return view('gas.create', compact('categories'));
    }

    // 顯示所有排放源類別的詳細資料
    public function details($id = null)
    {
        if ($id) {
            // 如果有提供 id，則只查詢該特定氣體
            $categories = Gas::where('G_id', $id)->paginate(1); // 每頁只顯示一筆
        } else {
            // 如果沒有提供 id，顯示所有氣體
            $categories = Gas::paginate(5); // 每頁顯示5筆資料
        }

        return view('gas.details', compact('categories'));
    }

}