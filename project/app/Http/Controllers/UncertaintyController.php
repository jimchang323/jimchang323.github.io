<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Uncertainty;
use App\Models\Company;
use App\Models\Fuel;

class UncertaintyController extends Controller
{
    /**
     * 儲存新不確定性資料
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        // 從 session 中獲取當前登入帳號（僅用於驗證）
        $account = $request->session()->get('logged_in_account');

        if (!$account) {
            return response()->json(['success' => false, 'message' => '請先登入'], 401);
        }

        // 根據帳號查詢公司資料（僅用於驗證）
        $company = Company::where('C_Account', $account)->first();

        if (!$company) {
            return response()->json(['success' => false, 'message' => '未找到相關公司資料'], 404);
        }

        // 驗證規則
        $request->validate([
            'F_id' => 'required|exists:Fuel,F_id',
            'Unc_Activity_lower_limit' => 'required|numeric',
            'Unc_Activity_upper_limit' => 'required|numeric',
            'Unc_Activitydata' => 'required|string|max:50',
            'Unc_regulations' => 'required|string|max:400',
            'Unc_Remark' => 'required|string|max:4000',
        ]);

        try {
            // 創建新不確定性資料
            $uncertainty = Uncertainty::create([
                'F_id' => $request->input('F_id'),
                'Unc_Activity_lower_limit' => $request->input('Unc_Activity_lower_limit'),
                'Unc_Activity_upper_limit' => $request->input('Unc_Activity_upper_limit'),
                'Unc_Activitydata' => $request->input('Unc_Activitydata'),
                'Unc_regulations' => $request->input('Unc_regulations'),
                'Unc_Remark' => $request->input('Unc_Remark'),
            ]);

            return response()->json([
                'success' => true,
                'message' => '不確定性資料已成功新增！',
                'uncertainty' => $uncertainty
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '不確定性資料新增失敗：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 顯示不確定性資料詳細資料
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\View\View
     */
    public function details(Request $request)
    {
        try {
            // 從 session 中獲取當前登入帳號（僅用於驗證）
            $account = $request->session()->get('logged_in_account');

            if (!$account) {
                return redirect()->route('company.login')->with('error', '請先登入');
            }

            // 根據帳號查詢公司資料（僅用於驗證）
            $company = Company::where('C_Account', $account)->first();

            if (!$company) {
                return redirect()->route('company.login')->with('error', '未找到相關公司資料');
            }

            // 查詢 Uncertainty 資料
            $uncertainties = Uncertainty::paginate(5);

            // 獲取所有 Fuel 資料（移除 C_ID 過濾）
            $fuels = Fuel::get(['F_id']);

            return view('uncertainty.details', compact('uncertainties', 'fuels'));
        } catch (\Exception $e) {
            // 記錄錯誤
            \Log::error('Error in UncertaintyController@details: ' . $e->getMessage());

            // 返回錯誤訊息給用戶
            return redirect()->route('uncertainty.details')->with('error', '無法載入資料，請稍後再試');
        }
    }

    /**
     * 更新不確定性資料
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        // 從 session 中獲取當前登入帳號（僅用於驗證）
        $account = $request->session()->get('logged_in_account');

        if (!$account) {
            return response()->json(['success' => false, 'message' => '請先登入'], 401);
        }

        // 根據帳號查詢公司資料（僅用於驗證）
        $company = Company::where('C_Account', $account)->first();

        if (!$company) {
            return response()->json(['success' => false, 'message' => '未找到相關公司資料'], 404);
        }

        try {
            // 查找資料
            $uncertainty = Uncertainty::findOrFail($id);

            // 驗證規則
            $request->validate([
                'F_id' => 'required|exists:Fuel,F_id',
                'Unc_Activity_lower_limit' => 'required|numeric',
                'Unc_Activity_upper_limit' => 'required|numeric',
                'Unc_Activitydata' => 'required|string|max:50',
                'Unc_regulations' => 'required|string|max:400',
                'Unc_Remark' => 'required|string|max:4000',
            ]);

            // 更新欄位
            $uncertainty->update([
                'F_id' => $request->F_id,
                'Unc_Activity_lower_limit' => $request->Unc_Activity_lower_limit,
                'Unc_Activity_upper_limit' => $request->Unc_Activity_upper_limit,
                'Unc_Activitydata' => $request->Unc_Activitydata,
                'Unc_regulations' => $request->Unc_regulations,
                'Unc_Remark' => $request->Unc_Remark,
            ]);

            return response()->json(['success' => true, 'message' => '更新成功']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}