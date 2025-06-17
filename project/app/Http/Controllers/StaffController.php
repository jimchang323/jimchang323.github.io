<?php

namespace App\Http\Controllers;

use App\Models\Staff;
use App\Models\Company;
use App\Models\Factory_area;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class StaffController extends Controller
{
    /**
     * 顯示員工管理頁面，根據登入帳號過濾資料
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

        $company = Company::where('C_Account', $account)->first();

        if (!$company) {
            return redirect()->route('company.login')->with('error', '未找到相關公司資料');
        }

        // 權限控制：非公司管理員重定向
        if ($request->session()->get('user_role') !== 'company') {
            return redirect()->route('company.function.management');
        }

        $c_id = $company->C_ID;

        $staffs = Staff::with(['company', 'factory_area'])
            ->where('C_ID', $c_id);

        $searchField = $request->input('search_field');
        $searchQuery = $request->input('search_query');

        if (!empty($searchField) && $searchQuery !== null) {
            $validFields = ['S_number', 'S_Name', 'S_Mail'];
            if (in_array($searchField, $validFields)) {
                $staffs->where($searchField, 'like', '%' . $searchQuery . '%');
            }
        }

        $staffs = $staffs->paginate(5);
        $factoryAreas = Factory_area::where('C_ID', $c_id)->get();

        return view('Staff.staff_create', compact('staffs', 'factoryAreas', 'c_id', 'company'));
    }

    /**
     * 顯示員工管理頁面（包含新增表單和員工列表）
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function create(Request $request)
    {
        $account = $request->session()->get('logged_in_account');

        if (!$account) {
            return redirect()->route('company.login')->with('error', '請先登入');
        }

        $company = Company::where('C_Account', $account)->first();

        if (!$company) {
            return redirect()->route('company.login')->with('error', '未找到相關公司資料');
        }

        // 權限控制：非公司管理員重定向
        if ($request->session()->get('user_role') !== 'company') {
            return redirect()->route('company.function.management');
        }

        $c_id = $company->C_ID;

        $staffs = Staff::with(['company', 'factory_area'])
            ->where('C_ID', $c_id);

        $searchField = $request->input('search_field');
        $searchQuery = $request->input('search_query');

        if (!empty($searchField) && $searchQuery !== null) {
            $validFields = ['S_number', 'S_Name', 'S_Mail'];
            if (in_array($searchField, $validFields)) {
                $staffs->where($searchField, 'like', '%' . $searchQuery . '%');
            }
        }

        $staffs = $staffs->paginate(5);
        $factoryAreas = Factory_area::where('C_ID', $c_id)->get();

        return view('Staff.staff_create', compact('staffs', 'factoryAreas', 'c_id', 'company'));
    }

    /**
     * 儲存新員工資料
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $account = $request->session()->get('logged_in_account');

        if (!$account) {
            return response()->json(['success' => false, 'message' => '請先登入'], 401);
        }

        $company = Company::where('C_Account', $account)->first();

        if (!$company) {
            return response()->json(['success' => false, 'message' => '未找到相關公司資料'], 404);
        }

        // 權限控制：僅公司管理員可新增
        if ($request->session()->get('user_role') !== 'company') {
            return response()->json(['success' => false, 'message' => '無權限新增員工'], 403);
        }

        $c_id = $company->C_ID;

        try {
            $request->validate([
                'Fa_ID' => 'nullable|exists:Factory_area,Fa_ID',
                'S_number' => 'required|string|max:50|unique:staff,S_number',
                'S_Name' => 'required|string|max:50',
                'S_Address' => 'required|string|max:50',
                'S_Phone' => 'required|string|max:50',
                'S_Mail' => 'required|email|max:50',
                'S_department' => 'required|string|max:50',
                'S_extension' => 'required|string|max:50',
                'S_Account' => 'required|string|max:50|unique:staff,S_Account',
                'S_Password' => 'required|string',
            ]);

            $staff = Staff::create([
                'C_ID' => $c_id,
                'Fa_ID' => $request->input('Fa_ID'),
                'S_number' => $request->input('S_number'),
                'S_Name' => $request->input('S_Name'),
                'S_Address' => $request->input('S_Address'),
                'S_Phone' => $request->input('S_Phone'),
                'S_Mail' => $request->input('S_Mail'),
                'S_department' => $request->input('S_department'),
                'S_extension' => $request->input('S_extension'),
                'S_Account' => $request->input('S_Account'),
                'S_Password' => $request->input('S_Password'),
            ]);

            // 更新相關的 Factory_area 的 S_ID
            if ($request->has('Fa_ID')) {
                $factoryArea = Factory_area::find($request->input('Fa_ID'));
                if ($factoryArea && !$factoryArea->S_ID) {
                    $factoryArea->update(['S_ID' => $staff->S_ID]);
                }
            }

            return response()->json(['success' => true, 'message' => '資料已新增']);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => '儲存失敗',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '儲存失敗',
                'errors' => ['general' => $e->getMessage()]
            ], 500);
        }
    }

    /**
     * 更新員工資料
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $account = $request->session()->get('logged_in_account');

        if (!$account) {
            return response()->json(['success' => false, 'message' => '請先登入'], 401);
        }

        $company = Company::where('C_Account', $account)->first();

        if (!$company) {
            return response()->json(['success' => false, 'message' => '未找到相關公司資料'], 404);
        }

        if ($request->session()->get('user_role') !== 'company') {
            return response()->json(['success' => false, 'message' => '無權限更新員工資料'], 403);
        }

        $c_id = $company->C_ID;

        try {
            $request->validate([
                'Fa_ID' => 'nullable|exists:Factory_area,Fa_ID',
                'S_number' => 'required|string|max:50|unique:staff,S_number,' . $id . ',S_ID',
                'S_Name' => 'required|string|max:50',
                'S_Address' => 'required|string|max:50',
                'S_Phone' => 'required|string|max:50',
                'S_Mail' => 'required|email|max:50',
                'S_department' => 'required|string|max:50',
                'S_extension' => 'required|string|max:50',
                'S_Account' => 'required|string|max:50|unique:staff,S_Account,' . $id . ',S_ID',
                'S_Password' => 'nullable|string',
            ]);

            $staff = Staff::findOrFail($id);

            if ($staff->C_ID != $c_id) {
                return response()->json([
                    'success' => false,
                    'message' => '無權限更新此員工資料',
                ], 403);
            }

            $oldFaId = $staff->Fa_ID;
            $newFaId = $request->input('Fa_ID');

            // 準備更新資料
            $updateData = $request->only([
                'S_number', 'S_Name', 'S_Address', 'S_Phone', 'S_Mail',
                'S_department', 'S_extension', 'S_Account', 'S_Password'
            ]);
            $updateData['C_ID'] = $c_id;
            if ($request->has('Fa_ID')) $updateData['Fa_ID'] = $newFaId;

            $staff->update($updateData);

            // 處理廠區負責人更新
            if ($newFaId && $newFaId != $oldFaId) {
                // 清除舊廠區的負責人
                if ($oldFaId) {
                    $oldFactoryArea = Factory_area::find($oldFaId);
                    if ($oldFactoryArea && $oldFactoryArea->S_ID == $staff->S_ID) {
                        $oldFactoryArea->update(['S_ID' => null]);
                    }
                }
                // 設置新廠區的負責人
                $newFactoryArea = Factory_area::find($newFaId);
                if ($newFactoryArea) {
                    // 確保新廠區沒有其他負責人
                    $existingStaff = Staff::where('Fa_ID', $newFaId)->where('S_ID', '!=', $staff->S_ID)->first();
                    if ($existingStaff) {
                        $existingStaff->update(['Fa_ID' => null]);
                        $existingFactoryArea = Factory_area::find($newFaId);
                        if ($existingFactoryArea) $existingFactoryArea->update(['S_ID' => null]);
                    }
                    $newFactoryArea->update(['S_ID' => $staff->S_ID]);
                }
            } elseif ($newFaId === null && $oldFaId) {
                // 移除負責人身份
                $oldFactoryArea = Factory_area::find($oldFaId);
                if ($oldFactoryArea && $oldFactoryArea->S_ID == $staff->S_ID) {
                    $oldFactoryArea->update(['S_ID' => null]);
                }
            }

            return response()->json(['success' => true, 'message' => '資料已更新']);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => '儲存失敗',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '儲存失敗',
                'errors' => ['general' => $e->getMessage()]
            ], 500);
        }
    }

    /**
     * 獲取員工資料以供編輯
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit(Request $request, $id)
    {
        $account = $request->session()->get('logged_in_account');

        if (!$account) {
            return response()->json(['success' => false, 'message' => '請先登入'], 401);
        }

        $company = Company::where('C_Account', $account)->first();

        if (!$company) {
            return response()->json(['success' => false, 'message' => '未找到相關公司資料'], 404);
        }

        // 權限控制：僅公司管理員可編輯
        if ($request->session()->get('user_role') !== 'company') {
            return response()->json(['success' => false, 'message' => '無權限編輯員工資料'], 403);
        }

        $c_id = $company->C_ID;

        $staff = Staff::with('factory_area')->findOrFail($id);

        if ($staff->C_ID != $c_id) {
            return response()->json([
                'success' => false,
                'message' => '無權限編輯此員工資料',
            ], 403);
        }

        return response()->json(['staff' => $staff]);
    }

    /**
     * 刪除員工資料
     *
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Request $request, $id)
    {
        $account = $request->session()->get('logged_in_account');

        if (!$account) {
            return redirect()->route('company.login')->with('error', '請先登入');
        }

        $company = Company::where('C_Account', $account)->first();

        if (!$company) {
            return redirect()->route('company.login')->with('error', '未找到相關公司資料');
        }

        // 權限控制：僅公司管理員可刪除
        if ($request->session()->get('user_role') !== 'company') {
            return redirect()->route('company.function.management')->with('error', '無權限刪除員工資料');
        }

        $c_id = $company->C_ID;

        $staff = Staff::findOrFail($id);

        if ($staff->C_ID != $c_id) {
            return redirect()->route('staff.create')->with('error', '無權限刪除此員工資料');
        }

        $fa_id = $staff->Fa_ID;
        $staff->delete();

        // 清除相關 Factory_area 的 S_ID
        if ($fa_id) {
            $factoryArea = Factory_area::find($fa_id);
            if ($factoryArea && !$staff->where('Fa_ID', $fa_id)->where('S_ID', '!=', $staff->S_ID)->exists()) {
                $factoryArea->update(['S_ID' => null]);
            }
        }

        return redirect()->route('staff.create')->with('success', '員工資料已刪除');
    }
}