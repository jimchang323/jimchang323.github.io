<?php

namespace App\Http\Controllers;

use App\Models\Equipment_inventory_details as EquipmentInventoryModel;
use App\Models\Company;
use App\Models\Factory_Equipment_Emission_Sources;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

class Equipment_inventory_details extends Controller
{
    public function index(Request $request)
    {
        $account = $request->session()->get('logged_in_account');
        $c_id = $request->session()->get('C_ID');
        $user_role = $request->session()->get('user_role');

        if (!$account || !$c_id) {
            Log::warning('Session 數據缺失，時間: ' . now(), ['account' => $account, 'C_ID' => $c_id]);
            if ($request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Session 過期，請重新登入'], 401);
            }
            return redirect()->route('company.login')->with('error', '請先登入');
        }

        $company = Company::where('C_ID', $c_id)->first();
        if (!$company) {
            Log::warning('未找到公司資料，時間: ' . now(), ['C_ID' => $c_id]);
            if ($request->ajax()) {
                return response()->json(['success' => false, 'message' => '未找到相關公司資料'], 404);
            }
            return redirect()->route('company.login')->with('error', '未找到相關公司資料');
        }

        $feeId = $request->input('FEE_id');
        Log::info('Equipment_inventory_details::index - FEE_id from request: ' . ($feeId ?: 'null') . ', 時間: ' . now());

        // 處理 validate_fee_id
        if ($request->ajax() && $request->input('action') === 'validate_fee_id') {
            $feeId = $request->input('FEE_id');
            $exists = Factory_Equipment_Emission_Sources::where('FEE_id', $feeId)->exists();
            Log::info('驗證 FEE_id:', ['FEE_id' => $feeId, 'exists' => $exists, 'time' => now()]);
            return response()->json(['exists' => $exists]);
        }

        // 處理 AJAX 請求（action=add）
        if ($request->ajax() && $request->input('action') === 'add') {
            $feeId = $request->input('FEE_id');
            Log::info('AJAX 請求 - action=add，FEE_id: ' . ($feeId ?: 'null'), ['time' => now()]);

            if (!$feeId) {
                Log::error('AJAX 請求缺少 FEE_id', ['time' => now()]);
                return response()->json(['success' => false, 'message' => '缺少排放源編號'], 400);
            }

            try {
                $emissionSource = Factory_Equipment_Emission_Sources::where('FEE_id', $feeId)
                    ->leftJoin('Factory_Equipment', 'Factory_Equipment_Emission_Sources.FE_ID', '=', 'Factory_Equipment.FE_ID')
                    ->leftJoin('Staff', 'Factory_Equipment_Emission_Sources.S_ID', '=', 'Staff.S_ID')
                    ->leftJoin('Category', 'Factory_Equipment_Emission_Sources.Cat_id', '=', 'Category.Cat_id')
                    ->leftJoin('Subcategory', 'Factory_Equipment_Emission_Sources.Sub_id', '=', 'Subcategory.Sub_id')
                    ->leftJoin('Gas', 'Factory_Equipment_Emission_Sources.G_id', '=', 'Gas.G_id')
                    ->leftJoin('EmissionSourceCategory', 'Factory_Equipment_Emission_Sources.ESC_id', '=', 'EmissionSourceCategory.ESC_id')
                    ->leftJoin('Fuel', 'Factory_Equipment_Emission_Sources.F_id', '=', 'Fuel.F_id')
                    ->leftJoin('EX_Emission_coefficient', 'Factory_Equipment_Emission_Sources.EX_ELE_REF_ID', '=', 'EX_Emission_coefficient.EX_ID')
                    ->select(
                        'Factory_Equipment_Emission_Sources.FEE_id',
                        'Factory_Equipment.FE_Name',
                        'Staff.S_Name',
                        'Category.Cat_CName',
                        'Subcategory.Sub_CName',
                        'Gas.G_CName',
                        'EmissionSourceCategory.ESC_CName',
                        'Fuel.F_CName',
                        'EX_Emission_coefficient.EX_recommendation'
                    )
                    ->first();

                if (!$emissionSource) {
                    Log::warning('未找到 FEE_id 數據', ['FEE_id' => $feeId, 'time' => now()]);
                    return response()->json(['success' => false, 'message' => '無效的排放源編號'], 404);
                }

                $preFilledData = [
                    'FEE_id' => $emissionSource->FEE_id,
                    'FE_Name' => $emissionSource->FE_Name ?? $request->input('FE_Name', ''),
                    'S_Name' => $emissionSource->S_Name ?? $request->input('S_Name', ''),
                    'Cat_CName' => $emissionSource->Cat_CName ?? $request->input('Cat_CName', ''),
                    'Sub_CName' => $emissionSource->Sub_CName ?? $request->input('Sub_CName', ''),
                    'G_CName' => $emissionSource->G_CName ?? $request->input('G_CName', ''),
                    'ESC_CName' => $emissionSource->ESC_CName ?? $request->input('ESC_CName', ''),
                    'F_CName' => $emissionSource->F_CName ?? $request->input('F_CName', ''),
                    'EX_recommendation' => $emissionSource->EX_recommendation ?? $request->input('EX_recommendation', '')
                ];

                Log::info('AJAX 請求 - 返回 preFilledData', ['preFilledData' => $preFilledData, 'time' => now()]);
                return response()->json([
                    'success' => true,
                    'preFilledData' => $preFilledData
                ])->withHeaders(['Cache-Control' => 'no-store, no-cache, must-revalidate']);
            } catch (\Exception $e) {
                Log::error('AJAX 請求失敗: ' . $e->getMessage(), ['FEE_id' => $feeId, 'time' => now()]);
                return response()->json(['success' => false, 'message' => '無法載入排放源數據'], 500);
            }
        }

        // 現有查詢邏輯
        $allowedFields = ['EID_ID', 'Equipment_Inventory_Details.FEE_id'];
        $field = in_array($request->search_field, $allowedFields) ? $request->search_field : null;

        $query = EquipmentInventoryModel::query();

        if ($feeId) {
            $query->where('Equipment_Inventory_Details.FEE_id', $feeId);
        }

        if ($request->has('search_field') && $request->has('search_query')) {
            $queryValue = $request->search_query;
            if ($field && $queryValue) {
                $query->where($field, 'LIKE', '%' . $queryValue . '%');
            }
        }

        $details = $query->join('Factory_Equipment_Emission_Sources', 'Equipment_Inventory_Details.FEE_id', '=', 'Factory_Equipment_Emission_Sources.FEE_id')
            ->leftJoin('Factory_Equipment', 'Factory_Equipment_Emission_Sources.FE_ID', '=', 'Factory_Equipment.FE_ID')
            ->leftJoin('Staff', 'Factory_Equipment_Emission_Sources.S_ID', '=', 'Staff.S_ID')
            ->leftJoin('Category', 'Factory_Equipment_Emission_Sources.Cat_id', '=', 'Category.Cat_id')
            ->leftJoin('Subcategory', 'Factory_Equipment_Emission_Sources.Sub_id', '=', 'Subcategory.Sub_id')
            ->leftJoin('Gas', 'Factory_Equipment_Emission_Sources.G_id', '=', 'Gas.G_id')
            ->leftJoin('EmissionSourceCategory', 'Factory_Equipment_Emission_Sources.ESC_id', '=', 'EmissionSourceCategory.ESC_id')
            ->leftJoin('Fuel', 'Factory_Equipment_Emission_Sources.F_id', '=', 'Fuel.F_id')
            ->leftJoin('EX_Emission_coefficient', 'Factory_Equipment_Emission_Sources.EX_ELE_REF_ID', '=', 'EX_Emission_coefficient.EX_ID')
            ->select(
                'Equipment_Inventory_Details.EID_ID',
                'Equipment_Inventory_Details.FEE_id',
                'Equipment_Inventory_Details.EID_Activity_data',
                'Equipment_Inventory_Details.EID_Active_data_unit',
                'Equipment_Inventory_Details.EID_CO2',
                'Equipment_Inventory_Details.EID_day',
                'Equipment_Inventory_Details.EID_Data_source',
                'Equipment_Inventory_Details.EID_Remark',
                'Equipment_Inventory_Details.EID_picture',
                'Factory_Equipment.FE_Name',
                'Staff.S_Name',
                'Category.Cat_CName',
                'Subcategory.Sub_CName',
                'Gas.G_CName',
                'EmissionSourceCategory.ESC_CName',
                'Fuel.F_CName',
                'EX_Emission_coefficient.EX_recommendation',
                'Factory_Equipment_Emission_Sources.GWP AS GWPD_report'
            )
            ->paginate(5);

        try {
            $factoryAreas = \App\Models\Factory_area::where('C_ID', $c_id)->get();
        } catch (\Exception $e) {
            $factoryAreas = collect();
            Log::error('無法載入 Factory_area: ' . $e->getMessage(), ['time' => now()]);
        }

        $company = (object) [
            'C_ID' => $company->C_ID,
            'C_Name' => $company->C_Name,
        ];

        $preFilledData = [
            'FEE_id' => $feeId ?? '',
            'FE_Name' => urldecode($request->input('FE_Name', '')),
            'S_Name' => urldecode($request->input('S_Name', '')),
            'Cat_CName' => urldecode($request->input('Cat_CName', '')),
            'Sub_CName' => urldecode($request->input('Sub_CName', '')),
            'G_CName' => urldecode($request->input('G_CName', '')),
            'ESC_CName' => urldecode($request->input('ESC_CName', '')),
            'F_CName' => urldecode($request->input('F_CName', '')),
            'EX_recommendation' => urldecode($request->input('EX_recommendation', '')),
            'GWPD_report' => urldecode($request->input('GWPD_report', ''))
        ];

        try {
            return view('Equipment_inventory_details.Equipment_inventory_details', compact('details', 'factoryAreas', 'company', 'user_role', 'feeId', 'preFilledData'));
        } catch (\Exception $e) {
            Log::error('視圖載入失敗: ' . $e->getMessage(), ['time' => now()]);
            if ($request->ajax()) {
                return response()->json(['success' => false, 'message' => '無法載入視圖'], 500);
            }
            return response()->json(['error' => '無法載入視圖'], 500);
        }
    }

    public function store(Request $request)
{
    $account = $request->session()->get('logged_in_account');
    $c_id = $request->session()->get('C_ID');

    if (!$account || !$c_id) {
        return response()->json(['success' => false, 'message' => '請先登入'], 401);
    }

    $company = Company::where('C_ID', $c_id)->first();
    if (!$company) {
        return response()->json(['success' => false, 'message' => '未找到相關公司資料'], 404);
    }

    // 記錄提交的數據以調試
    $inputData = $request->all();
    Log::info('接收到的表單數據:', ['data' => $inputData, 'time' => now()]);

    $validator = Validator::make($request->all(), [
        'FEE_id' => 'required|exists:Factory_Equipment_Emission_Sources,FEE_id',
        'EID_Activity_data' => 'required|numeric',
        'EID_Active_data_unit' => 'required|string|max:50',
        'EID_CO2' => 'required|numeric',
        'EID_day' => 'required|date',
        'EID_Data_source' => 'nullable|string|max:255',
        'EID_Remark' => 'nullable|string',
        'EID_picture' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
    ]);

    if ($validator->fails()) {
        Log::error('驗證失敗:', ['errors' => $validator->errors()->toArray()]);
        return response()->json(['success' => false, 'message' => '驗證失敗', 'errors' => $validator->errors()], 422);
    }

    try {
        $data = $request->only([
            'FEE_id', 'EID_Activity_data', 'EID_Active_data_unit', 'EID_CO2',
            'EID_day', 'EID_Data_source', 'EID_Remark'
        ]);

        // 設置預設值
        $data['EID_Active_data_unit'] = $data['EID_Active_data_unit'] ?? '公噸/年';
        $data['EID_Data_source'] = $data['EID_Data_source'] ?? '未知來源';
        $data['EID_Remark'] = $data['EID_Remark'] ?? '';

        if (\Schema::hasColumn('Equipment_Inventory_Details', 'C_ID')) {
            $data['C_ID'] = $c_id;
        }

        // 從 FEE_id 找到對應的 DBB_ID，並取得 Fa_ID
        $feeId = $request->input('FEE_id');
        Log::info('提交的 FEE_id', ['FEE_id' => $feeId, 'time' => now()]);
        $emissionSource = Factory_Equipment_Emission_Sources::where('FEE_id', $feeId)
            ->join('DBBoundary', 'Factory_Equipment_Emission_Sources.DBB_ID', '=', 'DBBoundary.DBB_ID')
            ->select('DBBoundary.Fa_ID')
            ->first();
        if (!$emissionSource || !$emissionSource->Fa_ID) {
            Log::error('未找到 Fa_ID', ['FEE_id' => $feeId, 'DBB_ID' => $emissionSource->DBB_ID ?? 'null', 'time' => now()]);
            return response()->json(['success' => false, 'message' => '無效的排放源或缺少廠區序號 (Fa_ID)'], 422);
        }
        $faId = $emissionSource->Fa_ID;
        Log::info('取得的 Fa_ID', ['Fa_ID' => $faId, 'time' => now()]);

        // 先創建記錄，獲取 EID_ID
        $data['EID_picture'] = ''; // 暫時設為空
        $equipment = EquipmentInventoryModel::create($data);
        $eid_id = $equipment->EID_ID;

        // 處理圖片上傳
        if ($request->hasFile('EID_picture')) {
            $file = $request->file('EID_picture');
            $companyName = preg_replace('/[^a-zA-Z0-9\-_]/', '_', $company->C_Name); // 清理公司名稱
            $basePath = base_path(); // 使用專案根目錄
            $companyFolderPath = $basePath . '/' . $companyName;
            $receiptFolderPath = $companyFolderPath . '/' . $faId . '/收據'; // 固定資料夾「收據」

            // 檢查公司名稱資料夾
            if (!File::exists($companyFolderPath)) {
                File::makeDirectory($companyFolderPath, 0755, true);
                Log::info('創建公司名稱資料夾', ['path' => $companyFolderPath, 'time' => now()]);
            } else {
                Log::info('公司名稱資料夾已存在', ['path' => $companyFolderPath, 'time' => now()]);
            }

            // 創建 Fa_ID/收據 資料夾
            if (!File::exists($receiptFolderPath)) {
                File::makeDirectory($receiptFolderPath, 0755, true);
                Log::info('創建收據資料夾', ['path' => $receiptFolderPath, 'time' => now()]);
            }

            // 儲存圖片
            $extension = $file->getClientOriginalExtension();
            $filename = "收據{$eid_id}.{$extension}";
            $file->move($receiptFolderPath, $filename);
            $data['EID_picture'] = $companyName . '/' . $faId . '/收據/' . $filename; // 儲存相對路徑
            Log::info('圖片上傳成功', ['file' => $filename, 'path' => $receiptFolderPath, 'time' => now()]);

            // 更新 EID_picture
            $equipment->EID_picture = $data['EID_picture'];
            $equipment->save();
        }

        // 處理 GWPD_report
        $data['GWPD_report'] = urldecode($request->input('GWPD_report', '0'));
        if ($data['GWPD_report'] !== '0') {
            $equipment->GWPD_report = $data['GWPD_report'];
            $equipment->save();
        }

        return response()->json(['success' => true, 'message' => '資料新增成功', 'FEE_id' => $data['FEE_id']]);
    } catch (\Exception $e) {
        Log::error('資料新增失敗', ['error' => $e->getMessage(), 'data' => $data, 'time' => now()]);
        return response()->json(['success' => false, 'message' => '新增失敗：' . $e->getMessage()], 500);
    }
}

    public function edit(Request $request, $id)
    {
        $account = $request->session()->get('logged_in_account');
        $c_id = $request->session()->get('C_ID');

        if (!$account || !$c_id) {
            Log::warning('未登入或缺少 C_ID', ['account' => $account, 'C_ID' => $c_id]);
            return response()->json(['success' => false, 'message' => '請先登入'], 401);
        }

        $company = Company::where('C_ID', $c_id)->first();
        if (!$company) {
            Log::warning('未找到公司資料', ['C_ID' => $c_id]);
            return response()->json(['success' => false, 'message' => '未找到相關公司資料'], 404);
        }

        if (!is_numeric($id)) {
            Log::error('無效的 EID_ID', ['id' => $id]);
            return response()->json(['success' => false, 'message' => '無效的資料 ID'], 400);
        }

        try {
            $detail = EquipmentInventoryModel::join('Factory_Equipment_Emission_Sources', 'Equipment_Inventory_Details.FEE_id', '=', 'Factory_Equipment_Emission_Sources.FEE_id')
                ->leftJoin('Factory_Equipment', 'Factory_Equipment_Emission_Sources.FE_ID', '=', 'Factory_Equipment.FE_ID')
                ->leftJoin('Staff', 'Factory_Equipment_Emission_Sources.S_ID', '=', 'Staff.S_ID')
                ->leftJoin('Category', 'Factory_Equipment_Emission_Sources.Cat_id', '=', 'Category.Cat_id')
                ->leftJoin('Subcategory', 'Factory_Equipment_Emission_Sources.Sub_id', '=', 'Subcategory.Sub_id')
                ->leftJoin('Gas', 'Factory_Equipment_Emission_Sources.G_id', '=', 'Gas.G_id')
                ->leftJoin('EmissionSourceCategory', 'Factory_Equipment_Emission_Sources.ESC_id', '=', 'EmissionSourceCategory.ESC_id')
                ->leftJoin('Fuel', 'Factory_Equipment_Emission_Sources.F_id', '=', 'Fuel.F_id')
                ->leftJoin('EX_Emission_coefficient', 'Factory_Equipment_Emission_Sources.EX_ELE_REF_ID', '=', 'EX_Emission_coefficient.EX_ID')
                ->where('Equipment_Inventory_Details.EID_ID', $id)
                ->select(
                    'Equipment_Inventory_Details.EID_ID',
                    'Equipment_Inventory_Details.FEE_id',
                    'Equipment_Inventory_Details.EID_Activity_data',
                    'Equipment_Inventory_Details.EID_Active_data_unit',
                    'Equipment_Inventory_Details.EID_CO2',
                    'Equipment_Inventory_Details.EID_day',
                    'Equipment_Inventory_Details.EID_Data_source',
                    'Equipment_Inventory_Details.EID_Remark',
                    'Equipment_Inventory_Details.EID_picture',
                    \DB::raw('COALESCE(Factory_Equipment.FE_Name, \'\') AS FE_Name'),
                    \DB::raw('COALESCE(Staff.S_Name, \'\') AS S_Name'),
                    \DB::raw('COALESCE(Category.Cat_CName, \'\') AS Cat_CName'),
                    \DB::raw('COALESCE(Subcategory.Sub_CName, \'\') AS Sub_CName'),
                    \DB::raw('COALESCE(Gas.G_CName, \'\') AS G_CName'),
                    \DB::raw('COALESCE(EmissionSourceCategory.ESC_CName, \'\') AS ESC_CName'),
                    \DB::raw('COALESCE(Fuel.F_CName, \'\') AS F_CName'),
                    \DB::raw('COALESCE(EX_Emission_coefficient.EX_recommendation, \'\') AS EX_recommendation'),
                    \DB::raw('COALESCE(Factory_Equipment_Emission_Sources.GWP, \'\') AS GWPD_report')
                )
                ->first();

            if (!$detail) {
                Log::warning('未找到設備盤查資料', ['EID_ID' => $id]);
                return response()->json(['success' => false, 'message' => '未找到指定的設備盤查資料'], 404);
            }

            if (\Schema::hasColumn('Equipment_Inventory_Details', 'C_ID') && isset($detail->C_ID) && $detail->C_ID != $c_id) {
                Log::warning('無權編輯其他公司資料', ['EID_ID' => $id, 'C_ID' => $c_id]);
                return response()->json(['success' => false, 'message' => '無權編輯此資料'], 403);
            }

            return response()->json([
                'success' => true,
                'detail' => [
                    'EID_ID' => $detail->EID_ID,
                    'FEE_id' => $detail->FEE_id,
                    'EID_Activity_data' => $detail->EID_Activity_data,
                    'EID_Active_data_unit' => $detail->EID_Active_data_unit,
                    'EID_CO2' => $detail->EID_CO2,
                    'EID_day' => $detail->EID_day,
                    'EID_Data_source' => $detail->EID_Data_source,
                    'EID_Remark' => $detail->EID_Remark,
                    'EID_picture' => $detail->EID_picture,
                    'FE_Name' => $detail->FE_Name,
                    'S_Name' => $detail->S_Name,
                    'Cat_CName' => $detail->Cat_CName,
                    'Sub_CName' => $detail->Sub_CName,
                    'G_CName' => $detail->G_CName,
                    'ESC_CName' => $detail->ESC_CName,
                    'F_CName' => $detail->F_CName,
                    'EX_recommendation' => $detail->EX_recommendation,
                    'GWPD_report' => $detail->GWPD_report,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('載入編輯資料失敗', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['success' => false, 'message' => '無法載入資料：' . $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
{
    $account = $request->session()->get('logged_in_account');
    $c_id = $request->session()->get('C_ID');

    if (!$account || !$c_id) {
        return response()->json(['success' => false, 'message' => '請先登入'], 401);
    }

    $company = Company::where('C_ID', $c_id)->first();
    if (!$company) {
        return response()->json(['success' => false, 'message' => '未找到相關公司資料'], 404);
    }

    // 記錄提交的數據以調試
    $inputData = $request->all();
    Log::info('接收到的表單數據:', ['data' => $inputData, 'time' => now()]);

    $validator = Validator::make($request->all(), [
        'FEE_id' => 'required|exists:Factory_Equipment_Emission_Sources,FEE_id',
        'EID_Activity_data' => 'required|numeric|min:0',
        'EID_Active_data_unit' => 'required|string|max:50',
        'EID_CO2' => 'required|numeric|min:0',
        'EID_day' => 'required|date',
        'EID_Data_source' => 'nullable|string|max:255',
        'EID_Remark' => 'nullable|string',
        'EID_picture' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
    ]);

    if ($validator->fails()) {
        Log::error('驗證失敗:', ['errors' => $validator->errors()->toArray(), 'time' => now()]);
        return response()->json(['success' => false, 'message' => '驗證失敗', 'errors' => $validator->errors()], 422);
    }

    try {
        $detail = EquipmentInventoryModel::findOrFail($id);
        if (\Schema::hasColumn('Equipment_Inventory_Details', 'C_ID') && $detail->C_ID != $c_id) {
            Log::warning('無權修改其他公司數據', ['EID_ID' => $id, 'C_ID' => $c_id, 'time' => now()]);
            return response()->json(['success' => false, 'message' => '無權修改其他公司數據'], 403);
        }

        $data = $request->only([
            'FEE_id', 'EID_Activity_data', 'EID_Active_data_unit', 'EID_CO2',
            'EID_day', 'EID_Data_source', 'EID_Remark'
        ]);

        // 設置預設值
        $data['EID_Active_data_unit'] = $data['EID_Active_data_unit'] ?? '公噸/年';
        $data['EID_CO2'] = $data['EID_CO2'] ?? 0;
        $data['EID_Data_source'] = $data['EID_Data_source'] ?? '';
        $data['EID_Remark'] = $data['EID_Remark'] ?? '';

        if (\Schema::hasColumn('Equipment_Inventory_Details', 'C_ID')) {
            $data['C_ID'] = $c_id;
        }

        // 從 FEE_id 找到對應的 DBB_ID，並取得 Fa_ID
        $feeId = $request->input('FEE_id');
        Log::info('提交的 FEE_id', ['FEE_id' => $feeId, 'time' => now()]);
        $emissionSource = Factory_Equipment_Emission_Sources::where('FEE_id', $feeId)
            ->join('DBBoundary', 'Factory_Equipment_Emission_Sources.DBB_ID', '=', 'DBBoundary.DBB_ID')
            ->select('DBBoundary.Fa_ID')
            ->first();
        if (!$emissionSource || !$emissionSource->Fa_ID) {
            Log::error('未找到 Fa_ID', ['FEE_id' => $feeId, 'DBB_ID' => $emissionSource->DBB_ID ?? 'null', 'time' => now()]);
            return response()->json(['success' => false, 'message' => '無效的排放源或缺少廠區序號 (Fa_ID)'], 422);
        }
        $faId = $emissionSource->Fa_ID;
        Log::info('取得的 Fa_ID', ['Fa_ID' => $faId, 'time' => now()]);

        // 處理圖片上傳
        if ($request->hasFile('EID_picture')) {
            $companyName = preg_replace('/[^a-zA-Z0-9\-_]/', '_', $company->C_Name); // 清理公司名稱
            $basePath = base_path(); // 使用專案根目錄
            $companyFolderPath = $basePath . '/' . $companyName;
            $receiptFolderPath = $companyFolderPath . '/' . $faId . '/收據'; // 固定資料夾「收據」

            // 檢查公司名稱資料夾
            if (!File::exists($companyFolderPath)) {
                File::makeDirectory($companyFolderPath, 0755, true);
                Log::info('創建公司名稱資料夾', ['path' => $companyFolderPath, 'time' => now()]);
            } else {
                Log::info('公司名稱資料夾已存在', ['path' => $companyFolderPath, 'time' => now()]);
            }

            // 創建 Fa_ID/收據 資料夾
            if (!File::exists($receiptFolderPath)) {
                File::makeDirectory($receiptFolderPath, 0755, true);
                Log::info('創建收據資料夾', ['path' => $receiptFolderPath, 'time' => now()]);
            }

            // 刪除舊圖片
            if ($detail->EID_picture) {
                $oldFilePath = $basePath . '/' . $detail->EID_picture;
                if (File::exists($oldFilePath)) {
                    File::delete($oldFilePath);
                    Log::info('成功刪除舊圖片', ['file' => $detail->EID_picture, 'time' => now()]);
                }
            }

            // 儲存新圖片
            $file = $request->file('EID_picture');
            $extension = $file->getClientOriginalExtension();
            $filename = "收據{$id}.{$extension}_{$id}";
            $file->move($receiptFolderPath, $filename);
            $data['EID_picture'] = $companyName . '/' . $faId . '/收據/' . $filename; // 儲存相對路徑
            Log::info('成功上傳新圖片', ['file' => $filename, 'path' => $receiptFolderPath, 'time' => now()]);
        }

        $detail->update($data);
        Log::info('資料更新成功', ['EID_ID' => $id, 'data' => $data, 'time' => now()]);

        return response()->json(['success' => true, 'message' => '資料更新成功']);
    } catch (\Exception $e) {
        Log::error('資料更新失敗', ['id' => $id, 'error' => $e->getMessage(), 'time' => now()]);
        return response()->json(['success' => false, 'message' => '更新失敗：' . $e->getMessage()], 500);
    }
}

    public function destroy(Request $request, $id)
    {
        $account = $request->session()->get('logged_in_account');
        $c_id = $request->session()->get('C_ID');
    
        if (!$account || !$c_id) {
            Log::warning('Session 數據缺失', ['account' => $account, 'C_ID' => $c_id]);
            return redirect()->route('company.login')->with('error', '請先登入');
        }
    
        $company = Company::where('C_ID', $c_id)->first();
        if (!$company) {
            Log::warning('未找到公司資料', ['C_ID' => $c_id]);
            return redirect()->route('company.login')->with('error', '未找到相關公司資料');
        }
    
        try {
            $detail = EquipmentInventoryModel::findOrFail($id);
            if (\Schema::hasColumn('Equipment_Inventory_Details', 'C_ID') && $detail->C_ID != $c_id) {
                Log::warning('無權刪除', ['id' => $id, 'C_ID' => $c_id]);
                return redirect()->route('equipment_inventory_details.index')->with('error', '無權刪除其他公司數據');
            }
    
            if ($detail->EID_picture) {
                $filePath = base_path($detail->EID_picture); // 使用相對路徑，例如 test/63/equipment_45.jpg
                if (File::exists($filePath)) {
                    File::delete($filePath);
                    Log::info('成功刪除圖片', ['file' => $filePath, 'time' => now()]);
                }
            }
    
            $detail->delete();
    
            return redirect()->route('equipment_inventory_details.index')->with('success', '資料刪除成功');
        } catch (\Exception $e) {
            Log::error('資料刪除失敗', ['id' => $id, 'error' => $e->getMessage()]);
            return redirect()->route('equipment_inventory_details.index')->with('error', '刪除失敗：' . $e->getMessage());
        }
    }
    /**
     * 提供圖片檔案給前端訪問
     *
     * @param  string  $path
     * @return \Illuminate\Http\Response
     */
    public function showImage($path)
    {
        $path = str_replace('%20', ' ', $path);
        $filePath = base_path($path);
        if (!File::exists($filePath)) {
            Log::error('圖片檔案不存在', ['path' => $filePath, 'time' => now()]);
            abort(404, '圖片不存在');
        }
        $mimeType = File::mimeType($filePath);
        return response()->file($filePath, [
            'Content-Type' => $mimeType,
        ]);
    }
}