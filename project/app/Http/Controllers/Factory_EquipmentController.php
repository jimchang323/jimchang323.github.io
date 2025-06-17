<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Factory_Equipment;
use App\Models\Company;
use App\Models\Factory_area;
use App\Models\Staff;

class Factory_EquipmentController extends Controller
{
    /**
     * 儲存新排放設備資料
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        // 從 session 中獲取當前登入帳號（主要版本）
        $account = $request->session()->get('logged_in_account');

        if (!$account) {
            return response()->json(['success' => false, 'message' => '請先登入'], 401);
        }

        // 根據帳號查詢對應的 C_ID（主要版本）
        $company = Company::where('C_Account', $account)->first();

        if (!$company) {
            return response()->json(['success' => false, 'message' => '未找到相關公司資料'], 404);
        }

        $c_id = $company->C_ID;

        // 權限控制：僅公司管理員可新增
        if ($request->session()->get('user_role') !== 'company') {
            return response()->json(['success' => false, 'message' => '無權限新增排放設備'], 403);
        }

        // 驗證規則（要合併版本）
        $request->validate([
            'C_ID' => 'required|exists:Company,C_ID', // 保留驗證，但實際由程式控制
            'Fa_ID' => 'required|exists:Factory_area,Fa_ID',
            'S_ID' => 'required|exists:Staff,S_ID',
            'FE_number' => 'required|string|max:50',
            'FE_Name' => 'required|string|max:50',
            'FE_type' => 'required|string|max:50',
            'FE_brand' => 'required|string|max:50',
            'FE_CName' => 'required|string|max:50',
            'FE_EName' => 'required|string|max:50',
            'FE_Status_stop' => 'nullable|boolean',
        ]);
        
        try {
            // 欄位設置（主要版本）
            $category = Factory_Equipment::create([
                'C_ID' => $c_id, // 自動設定 C_ID（主要版本）
                'Fa_ID' => $request->input('Fa_ID'),
                'S_ID' => $request->input('S_ID'),
                'FE_number' => $request->input('FE_number'),
                'FE_Name' => $request->input('FE_Name'),
                'FE_type' => $request->input('FE_type'),
                'FE_brand' => $request->input('FE_brand'),
                'FE_CName' => $request->input('FE_CName'),
                'FE_EName' => $request->input('FE_EName', ''),
                'FE_Status_stop' => $request->input('FE_Status_stop', 1)
            ]);

            return response()->json([
                'success' => true,
                'message' => '排放設備已成功新增！',
                'category' => $category
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '排放設備新增失敗：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 切換排放設備的啟用/停用狀態
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $FE_Status_stop
     * @return \Illuminate\Http\JsonResponse
     */
    public function toggle(Request $request, $FE_Status_stop)
    {
        // 從 session 中獲取當前登入帳號（主要版本）
        $account = $request->session()->get('logged_in_account');

        if (!$account) {
            return response()->json(['success' => false, 'message' => '請先登入'], 401);
        }

        // 根據帳號查詢對應的 C_ID（主要版本）
        $company = Company::where('C_Account', $account)->first();

        if (!$company) {
            return response()->json(['success' => false, 'message' => '未找到相關公司資料'], 404);
        }

        $c_id = $company->C_ID;

        // 權限控制：僅公司管理員可切換狀態
        if ($request->session()->get('user_role') !== 'company') {
            return response()->json(['success' => false, 'message' => '無權限切換設備狀態'], 403);
        }

        try {
            // 根據 C_ID 過濾資料（主要版本）
            $category = Factory_Equipment::where('C_ID', $c_id)->findOrFail($FE_Status_stop);
            $newStatus = $request->input('FE_Status_stop');
    
            if (!in_array($newStatus, [0, 1])) {
                return response()->json([
                    'success' => false,
                    'message' => '無效的狀態值。',
                ], 400);
            }
    
            $category->FE_Status_stop = $newStatus;
            $category->save();
    
            return response()->json([
                'success' => true,
                'newStatus' => $category->FE_Status_stop,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '更新失敗：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 顯示公司廠區排放設備詳細資料，根據登入帳號過濾資料
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function details(Request $request)
    {
        // 從 session 中獲取當前登入帳號（主要版本）
        $account = $request->session()->get('logged_in_account');

        if (!$account) {
            return redirect()->route('company.login')->with('error', '請先登入');
        }

        // 根據帳號查詢對應的 C_ID（主要版本）
        $company = Company::where('C_Account', $account)->first();

        if (!$company) {
            return redirect()->route('company.login')->with('error', '未找到相關公司資料');
        }

        $c_id = $company->C_ID;

        // 權限控制：非公司管理員重定向
        if ($request->session()->get('user_role') !== 'company') {
            return redirect()->route('company.function.management');
        }

        // 查詢 Factory_Equipment 資料，根據 C_ID 過濾並加入排序
        $validSortFields = ['FE_id', 'C_ID', 'Fa_ID', 'FE_number', 'FE_Name', 'FE_type', 'FE_brand', 'FE_CName', 'FE_EName', 'FE_Status_stop'];
        $sortField = $request->input('sort_field', 'FE_id');
        $sortDirection = strtolower($request->input('sort_direction', 'asc'));

        if (!in_array($sortField, $validSortFields)) {
            $sortField = 'FE_id';
        }
        if (!in_array($sortDirection, ['asc', 'desc'])) {
            $sortDirection = 'asc';
        }

        $query = Factory_Equipment::where('C_ID', $c_id)->with('staff'); // 載入 staff 關係
        $query->orderBy($sortField, $sortDirection);
        $categories = $query->paginate(5);

        // 獲取下拉選單資料（僅顯示與當前 C_ID 相關的廠區和員工）
        $factoryAreas = Factory_area::where('C_ID', $c_id)->get(['Fa_ID', 'F_Name']); // 加入 F_Name 供參考
        $staff = Staff::where('C_ID', $c_id)->get(['S_ID', 'S_Name']); // 加入 S_Name 供參考

        return view('factory_equipment.details', compact('categories', 'factoryAreas', 'staff', 'sortField', 'sortDirection'));
    }

    /**
     * 更新排放設備資料（要合併版本）
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        // 從 session 中獲取當前登入帳號（主要版本）
        $account = $request->session()->get('logged_in_account');

        if (!$account) {
            return response()->json(['success' => false, 'message' => '請先登入'], 401);
        }

        // 根據帳號查詢對應的 C_ID（主要版本）
        $company = Company::where('C_Account', $account)->first();

        if (!$company) {
            return response()->json(['success' => false, 'message' => '未找到相關公司資料'], 404);
        }

        $c_id = $company->C_ID;

        // 權限控制：僅公司管理員可更新
        if ($request->session()->get('user_role') !== 'company') {
            return response()->json(['success' => false, 'message' => '無權限更新設備資料'], 403);
        }

        try {
            // 根據 C_ID 過濾資料（主要版本）
            $equipment = Factory_Equipment::where('C_ID', $c_id)->findOrFail($id);
            // 更新欄位（要合併版本，但適應主要版本的欄位）
            $equipment->update([
                'Fa_ID' => $request->Fa_ID,
                'S_ID' => $request->S_ID,
                'FE_number' => $request->FE_number,
                'FE_Name' => $request->FE_Name,
                'FE_type' => $request->FE_type,
                'FE_brand' => $request->FE_brand,
                'FE_CName' => $request->FE_CName,
                'FE_EName' => $request->FE_EName,
            ]);

            return response()->json(['success' => true, 'message' => '更新成功']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}