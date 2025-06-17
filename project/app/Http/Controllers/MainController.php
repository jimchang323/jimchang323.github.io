<?php

namespace App\Http\Controllers;

use App\Models\Main;
use App\Models\Company;
use App\Models\Factory_area;
use App\Models\Staff;
use App\Models\CoefficientMain;
use App\Models\DBBoundary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;

class MainController extends Controller
{
    public function copy(Request $request, $id)
{
    try {
        $main = Main::findOrFail($id);

        // 創建新主檔記錄
        $newMain = $main->replicate();
        $newMain->save();

        // 複製相關的盤查地點記錄
        $boundaries = DbBoundary::where('M_ID', $id)->get();
        foreach ($boundaries as $boundary) {
            $newBoundary = $boundary->replicate();
            $newBoundary->M_ID = $newMain->M_ID; // 將新主檔的 M_ID 關聯
            $newBoundary->save();
        }

        return response()->json([
            'success' => true,
            'message' => '主檔及盤查地點資料已成功複製',
            'data' => $newMain
        ]);
    } catch (\Exception $e) {
        Log::error('主檔或盤查地點複製失敗: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => '複製失敗: ' . $e->getMessage()
        ], 500);
    }
}


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

    $c_id = $company->C_ID;

    $staffs = Staff::where('C_ID', $c_id)->get(['S_ID', 'S_Name', 'S_Account']);
    $coefficientMains = CoefficientMain::all();

    $query = Main::where('C_ID', $c_id);

    $searchField = $request->input('search_field', '');
    $searchQuery = $request->input('search_query', '');
    $statusFilter = $request->input('status_filter', 'all');
    $page = $request->input('page', 1); // 預設為第 1 頁

    Log::info('Pagination request', [
        'page' => $page,
        'search_field' => $searchField,
        'search_query' => $searchQuery,
        'status_filter' => $statusFilter
    ]);

    if ($searchField && $searchQuery) {
        $query->where($searchField, 'like', '%' . $searchQuery . '%');
    }

    if ($statusFilter !== 'all' && in_array($statusFilter, ['0', '1'])) {
        $query->where('M_Status_stop', $statusFilter);
    }

    $mains = $query->paginate(5)->appends([
        'search_field' => $searchField,
        'search_query' => $searchQuery,
        'status_filter' => $statusFilter
    ]);

    return view('Main.Main', compact('mains', 'company', 'c_id', 'staffs', 'coefficientMains'));
}

    public function store(Request $request)
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

            $validator = Validator::make($request->all(), [
                'C_ID' => 'required|string|exists:company,C_ID',
                'S_ID' => 'required|string|exists:staff,S_ID',
                'CM_ID' => 'required|string|exists:CoefficientMain,CM_ID',
                'M_Name' => 'required|string|max:255',
                'M_year' => 'required|string|max:4',
                'M_foundationyear' => 'required|string|max:4',
                'M_Industry' => 'required|string|max:255',
                'M_Status_stop' => 'required|in:0,1',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '儲存失敗',
                    'errors' => $validator->errors()
                ], 422);
            }

            $main = Main::create([
                'C_ID' => $request->C_ID,
                'S_ID' => $request->S_ID,
                'CM_ID' => $request->CM_ID,
                'M_Name' => $request->M_Name,
                'M_year' => $request->M_year,
                'M_foundationyear' => $request->M_foundationyear,
                'M_Industry' => $request->M_Industry,
                'M_Status_stop' => $request->M_Status_stop,
            ]);

            return response()->json([
                'success' => true,
                'message' => '主檔資料已成功新增',
                'data' => $main
            ]);
        } catch (\Exception $e) {
            Log::error('主檔儲存失敗: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => '儲存失敗',
                'errors' => ['general' => $e->getMessage()]
            ], 500);
        }
    }

    public function edit($id)
    {
        $main = Main::with(['company', 'staff', 'coefficient_main'])->findOrFail($id);
        return response()->json(['main' => $main]);
    }

    public function update(Request $request, $id)
    {
        try {
            $main = Main::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'C_ID' => 'required|string|exists:company,C_ID',
                'S_ID' => 'required|string|exists:staff,S_ID',
                'CM_ID' => 'required|string|exists:CoefficientMain,CM_ID',
                'M_Name' => 'required|string|max:255',
                'M_year' => 'required|string|max:4',
                'M_foundationyear' => 'required|string|max:4',
                'M_Industry' => 'required|string|max:255',
                'M_Status_stop' => 'required|in:0,1',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '更新失敗',
                    'errors' => $validator->errors()
                ], 422);
            }

            $main->update([
                'C_ID' => $request->C_ID,
                'S_ID' => $request->S_ID,
                'CM_ID' => $request->CM_ID,
                'M_Name' => $request->M_Name,
                'M_year' => $request->M_year,
                'M_foundationyear' => $request->M_foundationyear,
                'M_Industry' => $request->M_Industry,
                'M_Status_stop' => $request->M_Status_stop,
            ]);

            return response()->json([
                'success' => true,
                'message' => '主檔資料已成功更新',
                'data' => $main
            ]);
        } catch (\Exception $e) {
            Log::error('主檔更新失敗: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => '更新失敗',
                'errors' => ['general' => $e->getMessage()]
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $main = Main::findOrFail($id);
            $main->delete();
            return redirect()->route('main.create')->with('success', '主檔資料已成功刪除');
        } catch (\Exception $e) {
            return redirect()->route('main.create')->with('error', '刪除失敗：' . $e->getMessage());
        }
    }

    public function getCoefficientMain($id)
    {
        $coefficientMain = CoefficientMain::findOrFail($id);
        return response()->json(['coefficientMain' => $coefficientMain]);
    }

    public function toggle(Request $request, $id)
    {
        try {
            $main = Main::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'M_Status_stop' => 'required|in:0,1',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '無效的狀態值',
                    'errors' => $validator->errors()
                ], 422);
            }

            $main->M_Status_stop = $request->input('M_Status_stop');
            $main->save();

            return response()->json([
                'success' => true,
                'message' => '狀態更新成功'
            ]);
        } catch (\Exception $e) {
            Log::error('主檔狀態切換失敗: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => '狀態更新失敗：' . $e->getMessage()
            ], 500);
        }
    }
}