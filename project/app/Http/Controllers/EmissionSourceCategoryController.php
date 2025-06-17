<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\EmissionSourceCategory;

class EmissionSourceCategoryController extends Controller
{
    // 顯示新增表單
    public function create()
    {
        return view('EmissionSourceCategory.Emission_Source_Category_create');
    }

    // 處理表單提交並回傳 JSON
    public function store(Request $request)
    {
        $request->validate([
            'ESC_CName' => 'required|string|max:50',
            'ESC_EName' => 'nullable|string|max:50',
            'ESC_Status_stop' => 'nullable|boolean',
        ]);
        
        // 檢查是否有重複的排放源類別名稱
        $existingCategory = EmissionSourceCategory::where('ESC_CName', $request->input('ESC_CName'))->first();

        if ($existingCategory) {
            return response()->json([
                'success' => false,
                'message' => '排放源類別名稱重複！'
            ]);
        }

        try {
            // 建立新的排放源類別
            $category = EmissionSourceCategory::create([
                'ESC_CName' => $request->input('ESC_CName'),
                'ESC_EName' => $request->input('ESC_EName', ''),
                'ESC_Status_stop' => $request->input('ESC_Status_stop', 1)
            ]);

            return response()->json([
                'success' => true,
                'message' => '排放源類別名稱已成功新增！',
                'category' => $category
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '資料新增失敗：' . $e->getMessage()
            ], 500);
        }
    }

    // 切換啟用/停用狀態
    public function toggle(Request $request, $id)
    {
        try {
            $category = EmissionSourceCategory::findOrFail($id);
            
            // 從請求中獲取狀態值，並轉換為 1 或 0
            $newStatus = $request->input('ESC_Status_stop') ? 1 : 0;

            // 更新狀態
            $category->ESC_Status_stop = $newStatus;
            $category->save();

            return response()->json([
                'success' => true,
                'newStatus' => $category->ESC_Status_stop,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '更新失敗：' . $e->getMessage()
            ], 500);
        }
    }

    // 顯示所有排放源類別
    public function index()
    {
        $categories = EmissionSourceCategory::all();
        return view('EmissionSourceCategory.Emission_Source_Category', compact('categories'));
    }

    // 顯示所有排放源類別的詳細資料並處理搜尋與排序
    public function details(Request $request)
    {
        $query = EmissionSourceCategory::query();

        // 獲取搜尋參數
        $searchField = $request->input('search_field');
        $searchQuery = $request->input('search_query');
        $statusFilter = $request->input('status_filter', 'all');

        // 狀態篩選
        if ($statusFilter !== 'all') {
            $query->where('ESC_Status_stop', $statusFilter);
        }

        // 搜尋邏輯
        if ($searchQuery) {
            if ($searchField) {
                // 特定欄位查詢
                $query->where($searchField, 'like', "%{$searchQuery}%");
            } else {
                // 所有欄位查詢
                $query->where(function ($q) use ($searchQuery) {
                    $q->where('ESC_id', 'like', "%{$searchQuery}%")
                      ->orWhere('ESC_CName', 'like', "%{$searchQuery}%")
                      ->orWhere('ESC_EName', 'like', "%{$searchQuery}%");
                });
            }
        }

        // 排序邏輯
        $sortField = $request->input('sort_field');
        $sortDirection = $request->input('sort_direction', 'asc');
        if ($sortField && in_array($sortField, ['ESC_id', 'ESC_CName', 'ESC_EName'])) {
            $query->orderBy($sortField, $sortDirection);
        }

        $categories = $query->paginate(5);

        // 保留搜尋和排序參數到分頁連結
        if ($searchField || $searchQuery || $statusFilter !== 'all' || $sortField) {
            $categories->appends([
                'search_field' => $searchField,
                'search_query' => $searchQuery,
                'status_filter' => $statusFilter,
                'sort_field' => $sortField,
                'sort_direction' => $sortDirection,
            ]);
        }

        return view('EmissionSourceCategory.Emission_Source_Category_show', compact('categories'));
    }
}