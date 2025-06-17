<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Factory_area;
use App\Models\Company;
use App\Models\Staff;
use Illuminate\Support\Facades\Log;

class CompanyFunctionManagementController extends Controller
{
    /**
     * 顯示公司功能管理頁面，根據登入帳號過濾資料並設定登入身分
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

        Log::info('Logged in account: ' . $account); // 添加 debug 日誌

        $company = Company::where('C_Account', $account)->first();

        if ($company) {
            $c_id = $company->C_ID;
            $user_role = 'company';
            $display_identity = '登入身分: 公司管理員';
        } else {
            $staff = Staff::where('S_Account', $account)->first();
            if (!$staff) {
                Log::warning('No staff found for account: ' . $account);
                return redirect()->route('company.login')->with('error', '未找到相關帳號資料');
            }
            $c_id = $staff->C_ID;
            $user_role = 'staff';
            $s_id = $staff->S_ID;

            // 使用 Staff 表的 Fa_ID 查詢對應的 Factory_area
            $factoryArea = $staff->factory_area; // 通過關係動態獲取最新的廠區
            Log::info('Staff S_ID: ' . $s_id . ', Matched Factory Area: ' . ($factoryArea ? $factoryArea->F_Name : 'None')); // 添加 debug 日誌
            $display_identity = $factoryArea ? "登入身分: {$factoryArea->F_Name}負責人" : '登入身分: 員工(無職位)';

            // 權限控制：非公司管理員僅允許訪問 factory-area 頁面
            if ($user_role === 'staff' && $factoryArea) {
                return redirect()->route('factory.area', [
                    'page' => 3,
                    'sort_field' => 'Fa_ID',
                    'sort_direction' => 'asc'
                ])->with('display_identity', $display_identity);
            }
        }

        $query = Factory_area::where('C_ID', $c_id)->with('staff');

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

        return view('CompanyFunctionManagement.company_function_management', compact('factoryAreas', 'staffs', 'user_role', 'display_identity'));
    }
}