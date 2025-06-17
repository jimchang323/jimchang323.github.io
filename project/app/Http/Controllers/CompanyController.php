<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Company;
use App\Models\Staff;
use App\Models\Factory_area;

class CompanyController extends Controller
{
    /**
     * 處理登入表單提交
     */
    public function login(Request $request)
    {
        $validatedData = $request->validate([
            'C_Account' => 'required|string|max:20',
            'C_Password' => 'required|string|max:20',
        ]);

        $inputAccount = $request->input('C_Account');
        $inputPassword = $request->input('C_Password');

        // 檢查 Company 表的帳號和密碼（大小寫敏感）
        $company = Company::whereRaw('C_Account COLLATE Latin1_General_CS_AS = ?', [$inputAccount])
            ->whereRaw('C_Password COLLATE Latin1_General_CS_AS = ?', [$inputPassword])
            ->first();

        if ($company) {
            // 公司帳號登入成功，儲存帳號和 C_ID 到 session
            $request->session()->put('logged_in_account', $inputAccount);
            $request->session()->put('C_ID', $company->C_ID);
            $request->session()->put('user_role', 'company');
            $request->session()->put('user_identity', $company->C_Person); // 儲存 C_Person
            return redirect()->route('company.function.management');
        }

        // 檢查 Staff 表的帳號和密碼（大小寫敏感）
        $staff = Staff::whereRaw('S_Account COLLATE Latin1_General_CS_AS = ?', [$inputAccount])
            ->whereRaw('S_Password COLLATE Latin1_General_CS_AS = ?', [$inputPassword])
            ->first();

        if ($staff) {
            // 根據員工的 C_ID 查詢對應的公司
            $company = Company::where('C_ID', $staff->C_ID)->first();

            if ($company) {
                // 員工帳號登入成功，儲存帳號和 C_ID 到 session
                $request->session()->put('logged_in_account', $inputAccount);
                $request->session()->put('C_ID', $staff->C_ID);
                $request->session()->put('user_role', 'staff');
                $request->session()->put('user_identity', $staff->S_Name); // 儲存 S_Name
                return redirect()->route('company.function.management');
            } else {
                return redirect()->route('company.login')->with('error', '未找到相關公司資料');
            }
        }

        return redirect()->route('company.login')->with('error', '帳號或密碼錯誤');
    }

    public function index()
    {
        return view('Company.index');
    }

    public function showRegisterForm()
    {
        return view('Company.register', ['step' => 1]);
    }

    public function checkAccount(Request $request)
    {
        $request->validate([
            'C_Account' => 'required|string|max:20',
        ]);

        $account = $request->input('C_Account');
        $exists = Company::whereRaw('C_Account COLLATE Latin1_General_CS_AS = ?', [$account])->exists();

        if ($exists) {
            if ($request->ajax()) {
                return response()->json(['error' => '該帳號已被註冊，請重新創建']);
            }
            return redirect()->route('company.register')->with('error', '該帳號已被註冊，請重新創建');
        }

        $request->session()->put('C_Account', $account);
        if ($request->ajax()) {
            return response()->json(['success' => true]);
        }

        return view('Company.register', ['step' => 2]);
    }

    public function checkPassword(Request $request)
    {
        $request->validate([
            'C_Password' => 'required|string|max:20',
            'C_Confirm_Password' => 'required|string|same:C_Password',
        ]);

        $password = $request->input('C_Password');
        $exists = Company::whereRaw('C_Password COLLATE Latin1_General_CS_AS = ?', [$password])->exists();

        if ($exists) {
            if ($request->ajax()) {
                return response()->json(['error' => '該密碼已被使用，請選擇其他密碼']);
            }
            return redirect()->route('company.register')->with('error', '該密碼已被使用，請選擇其他密碼');
        }

        $request->session()->put('C_Password', $password);
        if ($request->ajax()) {
            return response()->json(['success' => true, 'redirect' => route('company.register') . '?step=3']);
        }

        return view('Company.register', ['step' => 3]);
    }

    public function previousStep1(Request $request)
    {
        $account = $request->session()->get('C_Account');
        return view('Company.register', ['step' => 1, 'C_Account' => $account]);
    }

    public function previousStep2(Request $request)
    {
        $password = $request->session()->get('C_Password');
        return view('Company.register', ['step' => 2, 'C_Password' => $password]);
    }

    public function register(Request $request)
    {
        $validatedData = $request->validate([
            'C_Name' => 'required|string|max:255',
            'C_UBN' => 'required|string|max:255',
            'C_CN' => 'required|string|max:255',
            'C_BN' => 'required|string|max:255',
            'C_Control' => 'required|string|max:255',
            'C_Addres' => 'required|string|max:255',
            'C_Phone' => 'required|string|max:20',
            'C_Person' => 'required|string|max:255',
            'C_People' => 'required|integer',
            'C_mail' => 'required|email|max:255',
            'C_money' => 'required|numeric',
            'C_Industry' => 'required|string|max:255',
            'C_remember' => 'nullable|boolean',
        ]);

        $account = $request->session()->get('C_Account');
        $password = $request->session()->get('C_Password');

        // 最終檢查帳號和密碼唯一性
        if (Company::whereRaw('C_Account COLLATE Latin1_General_CS_AS = ?', [$account])->exists()) {
            return redirect()->route('company.register')->with('error', '該帳號已被註冊，請重新創建');
        }
        if (Company::whereRaw('C_Password COLLATE Latin1_General_CS_AS = ?', [$password])->exists()) {
            return redirect()->route('company.register')->with('error', '該密碼已被使用，請選擇其他密碼');
        }

        $rememberValue = $request->has('C_remember') ? 1 : 0;

        Company::create([
            'C_Account' => $account,
            'C_Password' => $password, // 直接儲存明文
            'C_Name' => $validatedData['C_Name'],
            'C_UBN' => $validatedData['C_UBN'],
            'C_CN' => $validatedData['C_CN'],
            'C_BN' => $validatedData['C_BN'],
            'C_Control' => $validatedData['C_Control'],
            'C_Addres' => $validatedData['C_Addres'],
            'C_Phone' => $validatedData['C_Phone'],
            'C_Person' => $validatedData['C_Person'],
            'C_People' => $validatedData['C_People'],
            'C_mail' => $validatedData['C_mail'],
            'C_money' => $validatedData['C_money'],
            'C_Industry' => $validatedData['C_Industry'],
            'C_Status_stop' => 1,
            'C_remember' => $rememberValue,
        ]);

        $request->session()->forget(['C_Account', 'C_Password']);
        return redirect()->route('company.register')->with('success', '註冊成功！');
    }

    public function showLoginForm()
    {
        return view('Company.login');
    }

    /**
     * 顯示「編輯公司資料及廠區資料」頁面
     */
    public function area(Request $request)
    {
        $account = $request->session()->get('logged_in_account');
        $c_id = $request->session()->get('C_ID');
        $user_role = $request->session()->get('user_role');

        if (!$account || !$c_id) {
            return redirect()->route('company.login')->with('error', '請先登入');
        }

        // 權限控制：非公司管理員重定向
        if ($user_role !== 'company') {
            return redirect()->route('company.function.management');
        }

        // 查詢公司資料
        $company = Company::where('C_ID', $c_id)->first();

        if (!$company) {
            return redirect()->route('company.login')->with('error', '未找到相關公司資料');
        }

        // 查詢所有廠區及其負責人（只取第一個負責人）
        $factoryAreas = Factory_area::where('C_ID', $c_id)->with(['staff' => function ($query) {
            $query->whereNotNull('S_ID')->first(); // 確保只取單一記錄
        }])->get();

        return view('CompanyFunctionManagement.Company_area', compact('company', 'user_role', 'factoryAreas'));
    }

    /**
     * 顯示公司資料頁面
     */
    public function showCompanyData(Request $request)
    {
        $account = $request->session()->get('logged_in_account');
        $c_id = $request->session()->get('C_ID');
        $user_role = $request->session()->get('user_role');

        if (!$account || !$c_id) {
            return redirect()->route('company.login')->with('error', '請先登入');
        }

        // 權限控制：非公司管理員重定向
        if ($user_role !== 'company') {
            return redirect()->route('company.function.management');
        }

        // 查詢公司資料
        $company = Company::where('C_ID', $c_id)->first();

        if (!$company) {
            return redirect()->route('company.login')->with('error', '未找到相關公司資料');
        }

        // 查詢所有廠區及其負責人（只取第一個負責人）
        $factoryAreas = Factory_area::where('C_ID', $c_id)->with(['staff' => function ($query) {
            $query->select('S_ID', 'S_Name', 'Fa_ID')->whereNotNull('S_ID')->first(); // 確保只取單一記錄並選取必要欄位
        }])->get();

        return view('CompanyFunctionManagement.Company_area', compact('company', 'user_role', 'factoryAreas'));
    }

    /**
     * 顯示「設定廠區及設備資料」頁面
     */
    public function editFactory(Request $request)
    {
        $account = $request->session()->get('logged_in_account');
        $c_id = $request->session()->get('C_ID');
        $user_role = $request->session()->get('user_role');

        if (!$account || !$c_id) {
            return redirect()->route('company.login')->with('error', '請先登入');
        }

        // 權限控制：非公司管理員重定向
        if ($user_role !== 'company') {
            return redirect()->route('company.function.management');
        }

        // 查詢公司資料
        $company = Company::where('C_ID', $c_id)->first();

        if (!$company) {
            return redirect()->route('company.login')->with('error', '未找到相關公司資料');
        }

        return view('CompanyFunctionManagement.Edit_factory_data', compact('company', 'user_role'));
    }
}