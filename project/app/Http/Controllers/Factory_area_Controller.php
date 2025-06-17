<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Factory_area;
use App\Models\Company;
use App\Models\Staff;
use App\Models\FloorPlan;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class Factory_area_Controller extends Controller
{
    /**
     * 顯示廠區詳細資料頁面，根據登入帳號過濾資料
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function index(Request $request)
    {
        $account = $request->session()->get('logged_in_account');

        if (!$account) {
            return redirect()->route('company.login')->with('error', '請先登入');
        }

        Log::info('Logged in account: ' . $account);

        $company = Company::where('C_Account', $account)->first();

        if ($company) {
            $c_id = $company->C_ID;
            $user_role = 'company';
            $login_role = '公司管理員';
        } else {
            $staff = Staff::where('S_Account', $account)->first();
            if (!$staff) {
                Log::warning('No staff found for account: ' . $account);
                return redirect()->route('company.login')->with('error', '未找到相關帳號資料');
            }
            $c_id = $staff->C_ID;
            $user_role = 'staff';
            $factoryArea = Factory_area::where('C_ID', $c_id)->where('S_ID', $staff->S_ID)->first();
            Log::info('Staff S_ID: ' . $staff->S_ID . ', Matched Factory Area: ' . ($factoryArea ? $factoryArea->F_Name : 'None'));
            $login_role = $factoryArea ? "{$factoryArea->F_Name}負責人" : '員工(無職位)';
            if ($user_role === 'staff' && $factoryArea) {
                $query = Factory_area::where('Fa_ID', $factoryArea->Fa_ID)->with(['staff' => function ($query) {
                    $query->select('S_ID', 'S_Name', 'Fa_ID'); // 只選取需要的欄位
                }]);
            } else {
                return redirect()->route('company.function.management');
            }
        }

        $query = $query ?? Factory_area::where('C_ID', $c_id)->with(['staff' => function ($query) {
            $query->select('S_ID', 'S_Name', 'Fa_ID'); // 只選取需要的欄位
        }]);

        $validSortFields = ['Fa_ID', 'F_number', 'F_Name', 'F_Control', 'F_Address', 'F_Phone', 'F_Mail', 'F_People'];
        $sortField = $request->input('sort_field', 'Fa_ID');
        $sortDirection = strtolower($request->input('sort_direction', 'asc'));

        if (!in_array($sortField, $validSortFields)) {
            $sortField = 'Fa_ID';
        }
        if (!in_array($sortDirection, ['asc', 'desc'])) {
            $sortDirection = 'asc';
        }

        $query->orderBy($sortField, $sortDirection);
        $factoryAreas = $query->paginate(5);

        $staffs = Staff::where('C_ID', $c_id)->get(['S_number', 'S_Name']);

        return view('CompanyFunctionManagement.Factory_area', compact('factoryAreas', 'staffs', 'user_role', 'login_role'));
    }

    /**
     * 儲存廠區資料
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            $account = $request->session()->get('logged_in_account');

            if (!$account) {
                return response()->json(['success' => false, 'message' => '請先登入'], 401);
            }

            $company = Company::where('C_Account', $account)->first();

            if (!$company) {
                $staff = Staff::where('S_Account', $account)->first();
                if (!$staff) {
                    return response()->json(['success' => false, 'message' => '未找到相關帳號資料'], 404);
                }
                $c_id = $staff->C_ID;
            } else {
                $c_id = $company->C_ID;
            }

            $validator = Validator::make($request->all(), [
                'F_number' => 'nullable|string|max:50|exists:staff,S_number,C_ID,' . $c_id,
                'F_Name' => 'required|string|max:50',
                'F_Control' => 'required|string|max:50',
                'F_Address' => 'required|string|max:50',
                'F_Phone' => 'required|string|max:50',
                'F_Mail' => [
                    'required',
                    'string',
                    'max:50',
                    function ($attribute, $value, $fail) {
                        if (!str_contains($value, '@')) {
                            $fail('請在電子郵件中包含 \'@\'');
                        }
                        if (substr_count($value, '@') !== 1) {
                            $fail('電子郵件格式無效，應僅包含一個 \'@\'');
                        }
                    },
                ],
                'F_People' => 'required|string|regex:/^\d+$/|max:50',
            ], [
                'F_number.max' => '負責人編號最多 50 個字元',
                'F_number.exists' => '負責人編號不存在',
                'F_Name.required' => '廠區名稱為必填',
                'F_Name.max' => '廠區名稱最多 50 個字元',
                'F_Control.required' => '管制編號為必填',
                'F_Control.max' => '管制編號最多 50 個字元',
                'F_Address.required' => '廠區地址為必填',
                'F_Address.max' => '廠區地址最多 50 個字元',
                'F_Phone.required' => '聯絡電話為必填',
                'F_Phone.max' => '聯絡電話最多 50 個字元',
                'F_Mail.required' => '電子郵件為必填',
                'F_Mail.max' => '電子郵件最多 50 個字元',
                'F_People.required' => '員工人數為必填',
                'F_People.regex' => '員工人數必須為數字',
                'F_People.max' => '員工人數最多 50 個字元',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '儲存失敗',
                    'errors' => $validator->errors()
                ], 422);
            }

            $s_id = null;
            $f_number = $request->F_number ?? ''; // 如果 F_number 為 null，設為空字串
            if ($f_number) {
                $staff = Staff::where('S_number', $f_number)
                              ->where('C_ID', $c_id)
                              ->first();

                if (!$staff) {
                    return response()->json([
                        'success' => false,
                        'message' => '負責人編號無效，找不到對應的員工記錄',
                    ], 404);
                }
                $s_id = $staff->S_ID;
            }

            Log::info('Factory_area store request data:', $request->all());

            $factoryArea = Factory_area::create([
                'F_number' => $f_number,
                'F_Name' => $request->F_Name,
                'F_Control' => $request->F_Control,
                'F_Address' => $request->F_Address,
                'F_Phone' => $request->F_Phone,
                'F_Mail' => $request->F_Mail,
                'F_People' => $request->F_People,
                'C_ID' => $c_id,
                'S_ID' => $s_id,
            ]);

            // 如果指定了負責人，更新 Staff 的 Fa_ID
            if ($s_id) {
                $staff->update(['Fa_ID' => $factoryArea->Fa_ID]);
            }

            // 從 Staff 獲取負責人姓名（如果有）
            $staffName = $s_id ? Staff::where('S_ID', $s_id)->first()->S_Name : '-';

            return response()->json([
                'success' => true,
                'message' => '廠區資料已成功新增',
                'data' => [
                    'Fa_ID' => $factoryArea->Fa_ID,
                    'F_number' => $factoryArea->F_number,
                    'F_Name' => $factoryArea->F_Name,
                    'F_Control' => $factoryArea->F_Control,
                    'F_Address' => $factoryArea->F_Address,
                    'F_Phone' => $factoryArea->F_Phone,
                    'F_Mail' => $factoryArea->F_Mail,
                    'F_People' => $factoryArea->F_People,
                    'staff' => ['S_Name' => $staffName]
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Factory_area store failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => '儲存失敗',
                'errors' => ['general' => $e->getMessage()]
            ], 500);
        }
    }

    /**
     * 顯示編輯廠區資料的表單（返回 JSON 用於 AJAX）
     *
     * @param  int  $fa_id
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function edit($fa_id)
    {
        $account = request()->session()->get('logged_in_account');

        if (!$account) {
            return redirect()->route('company.login')->with('error', '請先登入');
        }

        $company = Company::where('C_Account', $account)->first();

        if (!$company) {
            return redirect()->route('company.login')->with('error', '未找到相關公司資料');
        }

        $c_id = $company->C_ID;

        // 權限控制：僅公司管理員可編輯
        if (request()->session()->get('user_role') !== 'company') {
            return redirect()->route('company.function.management');
        }

        $factoryArea = Factory_area::where('C_ID', $c_id)->findOrFail($fa_id);

        return response()->json([
            'success' => true,
            'factoryArea' => $factoryArea
        ]);
    }

    /**
     * 更新廠區資料
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $fa_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $fa_id)
    {
        try {
            $account = $request->session()->get('logged_in_account');

            if (!$account) {
                return response()->json(['success' => false, 'message' => '請先登入'], 401);
            }

            $company = Company::where('C_Account', $account)->first();

            if (!$company) {
                return response()->json(['success' => false, 'message' => '未找到相關公司資料'], 404);
            }

            $c_id = $company->C_ID;

            // 權限控制：僅公司管理員可更新
            if ($request->session()->get('user_role') !== 'company') {
                return response()->json(['success' => false, 'message' => '無權限更新廠區資料'], 403);
            }

            $factoryArea = Factory_area::where('C_ID', $c_id)->findOrFail($fa_id);

            $validator = Validator::make($request->all(), [
                'F_number' => 'nullable|string|max:50|exists:staff,S_number,C_ID,' . $c_id,
                'F_Name' => 'required|string|max:50',
                'F_Control' => 'required|string|max:50',
                'F_Address' => 'required|string|max:50',
                'F_Phone' => 'required|string|max:50',
                'F_Mail' => [
                    'required',
                    'string',
                    'max:50',
                    function ($attribute, $value, $fail) {
                        if (!str_contains($value, '@')) {
                            $fail('請在電子郵件中包含 \'@\'');
                        }
                        if (substr_count($value, '@') !== 1) {
                            $fail('電子郵件格式無效，應僅包含一個 \'@\'');
                        }
                    },
                ],
                'F_People' => 'required|string|regex:/^\d+$/|max:50',
            ], [
                'F_number.max' => '負責人編號最多 50 個字元',
                'F_number.exists' => '負責人編號不存在',
                'F_Name.required' => '廠區名稱為必填',
                'F_Name.max' => '廠區名稱最多 50 個字元',
                'F_Control.required' => '管制編號為必填',
                'F_Control.max' => '管制編號最多 50 個字元',
                'F_Address.required' => '廠區地址為必填',
                'F_Address.max' => '廠區地址最多 50 個字元',
                'F_Phone.required' => '聯絡電話為必填',
                'F_Phone.max' => '聯絡電話最多 50 個字元',
                'F_Mail.required' => '電子郵件為必填',
                'F_Mail.max' => '電子郵件最多 50 個字元',
                'F_People.required' => '員工人數為必填',
                'F_People.regex' => '員工人數必須為數字',
                'F_People.max' => '員工人數最多 50 個字元',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '更新失敗',
                    'errors' => $validator->errors()
                ], 422);
            }

            $s_id = null;
            $f_number = $request->F_number ?? ''; // 如果 F_number 為 null，設為空字串
            if ($f_number) {
                $staff = Staff::where('S_number', $f_number)
                              ->where('C_ID', $c_id)
                              ->first();

                if (!$staff) {
                    return response()->json([
                        'success' => false,
                        'message' => '負責人編號無效，找不到對應的員工記錄',
                    ], 404);
                }
                $s_id = $staff->S_ID;
            }

            // 儲存之前的老負責人資訊
            $oldSId = $factoryArea->S_ID;

            $factoryArea->update([
                'F_number' => $f_number,
                'F_Name' => $request->F_Name,
                'F_Control' => $request->F_Control,
                'F_Address' => $request->F_Address,
                'F_Phone' => $request->F_Phone,
                'F_Mail' => $request->F_Mail,
                'F_People' => $request->F_People,
                'S_ID' => $s_id,
            ]);

            // 如果指定了新負責人，更新 Staff 的 Fa_ID
            if ($s_id) {
                $staff->update(['Fa_ID' => $factoryArea->Fa_ID]);
            }

            // 如果之前有負責人且變更，清除舊負責人的 Fa_ID
            if ($oldSId && $oldSId != $s_id) {
                $oldStaff = Staff::find($oldSId);
                if ($oldStaff) {
                    $oldStaff->update(['Fa_ID' => null]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => '廠區資料已成功更新',
                'data' => $factoryArea
            ]);
        } catch (\Exception $e) {
            Log::error('Factory_area update failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => '更新失敗',
                'errors' => ['general' => $e->getMessage()]
            ], 500);
        }
    }

    /**
     * 刪除廠區資料
     *
     * @param  int  $fa_id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy($fa_id)
    {
        $account = request()->session()->get('logged_in_account');

        if (!$account) {
            return redirect()->route('company.login')->with('error', '請先登入');
        }

        $company = Company::where('C_Account', $account)->first();

        if (!$company) {
            return redirect()->route('company.login')->with('error', '未找到相關公司資料');
        }

        $c_id = $company->C_ID;

        // 權限控制：僅公司管理員可刪除
        if (request()->session()->get('user_role') !== 'company') {
            return redirect()->route('company.function.management');
        }

        $factoryArea = Factory_area::where('C_ID', $c_id)->findOrFail($fa_id);

        // 刪除相關的 FloorPlan 資料
        FloorPlan::where('C_ID', $c_id)->where('Fa_ID', $fa_id)->delete();

        // 檢查是否有相關聯的 Staff，並清除 Fa_ID
        $staff = Staff::where('Fa_ID', $fa_id)->first();
        if ($staff) {
            $staff->update(['Fa_ID' => null]);
        }

        $factoryArea->delete();

        return redirect()->route('factory.area')->with('success', '廠區資料及相關平面圖已成功刪除');
    }
}