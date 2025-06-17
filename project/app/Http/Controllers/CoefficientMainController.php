<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CoefficientMain;
use App\Models\ExEmissionCoefficient;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\OdsParserController;
use App\Http\Controllers\GWPController;
use App\Models\Gas;
use App\Models\Category;
use App\Models\Subcategory;
use App\Models\Fuel;
use App\Models\EmissionSourceCategory;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\Log;

class CoefficientMainController extends Controller
{
    public function index(Request $request)
    {
        // 修改為分頁查詢，每頁顯示 6 筆資料
        $coefficientMains = CoefficientMain::paginate(6);

        if ($request->is('upload')) {
            return view('upload', compact('coefficientMains'));
        } elseif ($request->is('coefficient-main')) {
            return view('CoefficientMain.coefficient_main', ['data' => $coefficientMains]);
        }

        return view('coefficient_main', ['data' => $coefficientMains]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'CM_order' => 'required|integer',
            'CM_Manage_Name' => 'required|string|max:255',
            'CM_Introduction' => 'required|string',
            'CM_law' => 'nullable|string|max:255',
            'CM_year' => 'required|integer',
            'CM_source' => 'nullable|string|url',
            'CM_Status_stop' => 'required|in:啟用,停用',
            'ods_file' => 'required|file|mimes:ods',
        ]);
    try {
        $status = $request->CM_Status_stop === '啟用' ? 1 : 0;
        // 如果新記錄設為啟用，檢查是否有相同的啟用序號
        if ($status === 1) {
            $existingOrder = CoefficientMain::where('CM_order', $request->CM_order)
                ->where('CM_Status_stop', 1)
                ->first();

            if ($existingOrder) {
                return back()->with('error', '已有啟用的相同序號記錄，請先停用該記錄');
            }
        }
            // 建立管理表記錄，先儲存不包含 CM_path
            $coefficientMain = CoefficientMain::create([
                'CM_order' => $request->CM_order,
                'CM_Manage_Name' => $request->CM_Manage_Name,
                'CM_Introduction' => $request->CM_Introduction,
                'CM_law' => $request->CM_law,
                'CM_year' => $request->CM_year,
                'CM_source' => $request->CM_source,
                'CM_Status_stop' => $status,
                'CM_path' => '', // 預設為空字串
            ]);

            // 儲存 ODS 檔案
            if ($request->hasFile('ods_file')) {
                $file = $request->file('ods_file');
                $fileName = "{$coefficientMain->CM_ID}-{$request->CM_order}.ods"; // 命名格式
                $absolutePath = base_path('ods_files'); // 存放到 Laravel 根目錄下的 ods_files

                // 確保 ods_files 資料夾存在
                if (!file_exists($absolutePath)) {
                    mkdir($absolutePath, 0755, true);
                }

                // 移動檔案到指定路徑
                $file->move($absolutePath, $fileName);
                $filePath = "ods_files/{$fileName}"; // 相對路徑

                // 更新 CM_path
                $coefficientMain->CM_path = $filePath;
                $coefficientMain->save();

            return redirect()->route('coefficient-main.list')->with('success', '檔案建立成功!');
        }

        return back()->with('error', '未選擇檔案上傳！');
    }   catch (\Exception $e) {
            return back()->with('error', '建立失敗: ' . $e->getMessage());
    }}


    public function update(Request $request, $id)
{
    $request->validate([
        'CM_order' => 'required|integer',
        'CM_Manage_Name' => 'required|string|max:255',
        'CM_Introduction' => 'required|string',
        'CM_law' => 'nullable|string|max:255',
        'CM_year' => 'required|integer',
        'CM_source' => 'nullable|string|url',
        'CM_Status_stop' => 'nullable', // 狀態為可選，因為 checkbox 未選中時不會提交
        'ods_file' => 'nullable|file|mimes:ods', // ODS 檔案為可選
    ]);

    try {
        $coefficientMain = CoefficientMain::findOrFail($id);

        // 處理狀態
        $status = $request->has('CM_Status_stop') ? 1 : 0;

        // 如果要啟用，檢查是否已有相同序號的啟用記錄
        if ($status === 1) {
            $existingOrder = CoefficientMain::where('CM_order', $request->CM_order)
                ->where('CM_Status_stop', 1)
                ->where('CM_ID', '!=', $id) // 排除當前記錄
                ->first();

            if ($existingOrder) {
                return back()->with('error', '已有啟用的相同序號記錄，請先停用該記錄');
            }
        }

        // 更新基本資料
        $coefficientMain->update([
            'CM_order' => $request->CM_order,
            'CM_Manage_Name' => $request->CM_Manage_Name,
            'CM_Introduction' => $request->CM_Introduction,
            'CM_law' => $request->CM_law,
            'CM_year' => $request->CM_year,
            'CM_source' => $request->CM_source,
            'CM_Status_stop' => $status,
        ]);

        // 處理 ODS 檔案上傳
        if ($request->hasFile('ods_file')) {
            $file = $request->file('ods_file');
            $fileName = "{$coefficientMain->CM_ID}-{$request->CM_order}.ods";
            $absolutePath = base_path('ods_files');

            // 確保 ods_files 資料夾存在
            if (!file_exists($absolutePath)) {
                mkdir($absolutePath, 0755, true);
            }

            // 刪除原有檔案（如果存在）
            $oldFilePath = base_path($coefficientMain->CM_path);
            if (file_exists($oldFilePath)) {
                unlink($oldFilePath);
            }

            // 移動新檔案並更新路徑
            $file->move($absolutePath, $fileName);
            $filePath = "ods_files/{$fileName}";
            $coefficientMain->CM_path = $filePath;
            $coefficientMain->save();
        }

        return redirect()->route('coefficient-main.show', $id)->with('success', '更新成功！');
    } catch (\Exception $e) {
        return back()->with('error', '更新失敗：' . $e->getMessage());
    }
}

    public function destroy($id)
    {
        $coefficientMain = CoefficientMain::findOrFail($id);
        $coefficientMain->delete();

        return redirect()->route('coefficient-main.index')->with('success', '刪除成功');
    }

    public function list(Request $request)
    {
        $query = CoefficientMain::query();

        // 獲取搜尋參數
        $searchField = $request->input('search_field');
        $searchQuery = $request->input('search_query');
        $statusFilter = $request->input('status_filter', 'all'); // 預設為 'all'
        Log::info('Request Params:', $request->all());
        // 狀態篩選
        if ($statusFilter !== 'all') {
            $query->where('CM_Status_stop', $statusFilter);
        }
        if ($searchQuery) {
            if ($searchField) {
                // 特定欄位查詢
                $query->where($searchField, 'like', "%{$searchQuery}%");
            } else {
                // 所有欄位查詢
                $query->where(function ($q) use ($searchQuery) {
                    $q->where('CM_ID', 'like', "%{$searchQuery}%")
                      ->orWhere('CM_order', 'like', "%{$searchQuery}%")
                      ->orWhere('CM_Manage_Name', 'like', "%{$searchQuery}%")
                      ->orWhere('CM_Introduction', 'like', "%{$searchQuery}%")
                      ->orWhere('CM_year', 'like', "%{$searchQuery}%");
                });
            }
        }

        // 排序邏輯
        $sortField = $request->input('sort_field');
        $sortDirection = $request->input('sort_direction', 'asc'); // 預設升序
        if ($sortField && in_array($sortField, ['CM_ID', 'CM_order', 'CM_year'])) {
            $query->orderBy($sortField, $sortDirection);
        }

        $data = $query->paginate(10);

        // 保留搜尋和排序參數
        if ($searchField || $searchQuery || $sortField) {
            $data->appends([
                'search_field' => $searchField,
                'search_query' => $searchQuery,
                'status_filter' => $statusFilter,
                'sort_field' => $sortField,
                'sort_direction' => $sortDirection,
            ]);
        }
        return view('CoefficientMain.coefficient_main_list', compact('data'));
    }

    public function parseOdsFile(Request $request, $CM_ID = null)
    {
        try {
            $coefficientMain = CoefficientMain::findOrFail($CM_ID);
            $filePath = base_path($coefficientMain->CM_path); // 絕對路徑
    
            if (!file_exists($filePath)) {
                return back()->with('error', 'ODS 檔案不存在');
            }
    
            $spreadsheet = IOFactory::load($filePath);
            // 後續解析邏輯保持不變
            return redirect()->route('gwp.details', ['id' => $CM_ID])->with('success', '解析成功');
        } catch (\Exception $e) {
            return back()->with('error', '解析失敗: ' . $e->getMessage());
        }
    }

    public function toggle(Request $request, $id)
    {
        $request->validate([
            'CM_Status_stop' => 'required|in:0,1'
        ]);

        $coefficient = CoefficientMain::findOrFail($id);
        $newStatus = $request->CM_Status_stop;

        // 如果要設為啟用 (1)，檢查是否有其他啟用的相同序號或名稱
        if ($newStatus === 1) {
            $existingOrder = CoefficientMain::where('CM_order', $coefficient->CM_order)
                ->where('CM_Status_stop', 1)
                ->where('CM_ID', '!=', $id) // 排除當前記錄
                ->first();

            if ($existingOrder) {
                return response()->json([
                    'success' => false,
                    'message' => '已有啟用的相同序號記錄，請先停用該記錄'
                ]);
            }
        }

        // 更新狀態
        $coefficient->CM_Status_stop = $newStatus;
        $coefficient->save();

        return response()->json([
            'success' => true,
            'message' => '狀態更新成功'
        ]);
    }

    public function checkCmOrder(Request $request)
    {
        $cmOrder = $request->input('CM_order');
        
        // 檢查是否存在任何記錄
        $exists = CoefficientMain::where('CM_order', $cmOrder)->exists();
        
        // 檢查是否有任何啟用的記錄
        $isEnabled = $exists && CoefficientMain::where('CM_order', $cmOrder)
            ->where('CM_Status_stop', 1)
            ->exists();
        
        // 記錄日誌以便除錯
        //\Log::info("Check CM_order: $cmOrder, Exists: " . ($exists ? 'true' : 'false') . ", IsEnabled: " . ($isEnabled ? 'true' : 'false'));
        
        return response()->json([
            'exists' => $exists,
            'isEnabled' => $isEnabled
        ]);
    }
    

    public function showMainData($id)
    {
        $coefficientMain = CoefficientMain::find($id);

        if (!$coefficientMain) {
            return redirect()->route('coefficient-main.index')->with('error', '資料未找到');
        }

        return view('CoefficientMain.show_main_data', ['coefficientMain' => $coefficientMain]);
    }

    public function parseOdsSheet(Request $request, $id, $sheet)
    {
        try {
            $coefficientMain = CoefficientMain::findOrFail($id);
            $odsFilePath = $coefficientMain->CM_path;

            if (!file_exists($odsFilePath)) {
                return redirect()->back()->with('error', 'ODS 檔案不存在');
            }

            $odsParser = new OdsParserController();
            // 這裡之後會根據 $sheet 參數呼叫解析特定表的邏輯
            // 例如：$odsParser->parseSpecificSheet($odsFilePath, $coefficientMain->CM_ID, $sheet);

            return redirect()->back()->with('success', "成功解析表單: {$sheet}");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', "解析失敗: " . $e->getMessage());
        }
    }
    public function downloadOdsFile($id)
    {
        $coefficientMain = CoefficientMain::findOrFail($id);
        $filePath = base_path($coefficientMain->CM_path); // 例如 "ods_files/1-1.ods"
        $fileName = "{$coefficientMain->CM_ID}-{$coefficientMain->CM_order}.ods";
    
        if (file_exists($filePath)) {
            return response()->download($filePath, $fileName);
        } else {
            return redirect()->back()->with('error', '檔案不存在');
        }
    }

    public function checkDeletedRecords()
{
    // 從 DeletionLog 表中獲取被刪除的記錄
    $deletedRecords = DB::table('DeletionLog')->get();

    foreach ($deletedRecords as $record) {
        $fileName = "{$record->CM_ID}-{$record->CM_order}.ods";
        $filePath = base_path("ods_files/{$fileName}");

        if (file_exists($filePath)) {
            try {
                unlink($filePath); // 刪除檔案
                Log::info("Deleted ODS file: {$filePath}");
            } catch (\Exception $e) {
                Log::error("Failed to delete ODS file: {$filePath} - Error: {$e->getMessage()}");
            }
        }

        // 刪除處理完的日誌記錄
        DB::table('DeletionLog')->where('LogID', $record->LogID)->delete();
    }
}
public function parseAll(Request $request, $id)
{
    try {
        $coefficientMain = CoefficientMain::findOrFail($id);
        $odsFilePath = base_path($coefficientMain->CM_path);

        if (!file_exists($odsFilePath)) {
            return response()->json(['success' => false, 'message' => 'ODS 檔案不存在']);
        }

        $forceUpdate = $request->input('force_update', false);
        $odsParser = new OdsParserController();
        $gwpParser = new GWPController();
        $spillParser = new SpillController();

        // 依次解析 CO2、CH4、N2O、GWP 和逸散
        $results = [
            'co2' => $odsParser->parseCO2Sheet($id, $request),
            'ch4' => $odsParser->parseCH4Sheet($id, $request),
            'n2o' => $odsParser->parseN2OSheet($id, $request),
            'gwp' => $gwpParser->parseODS($request, $id),
            'escape' => $spillParser->parseOdsSheet($request, $id),
        ];

        $allSuccess = true;
        $messages = [];
        foreach ($results as $type => $result) {
            try {
                if ($type === 'escape') {
                    // 確保逸散解析結果是 JSON 格式
                    if ($result instanceof \Illuminate\Http\JsonResponse) {
                        $resultData = $result->getData(true);
                    } else {
                        throw new \Exception('逸散解析未返回 JSON 格式回應');
                    }
                } else {
                    // 其他解析應返回 JSON 格式
                    if ($result instanceof \Illuminate\Http\JsonResponse) {
                        $resultData = json_decode($result->getContent(), true);
                    } else {
                        throw new \Exception("{$type} 解析未返回 JSON 格式回應");
                    }
                }

                if (!$resultData['success']) {
                    $allSuccess = false;
                    $messages[] = "$type 解析失敗：" . ($resultData['message'] ?? '未知錯誤');
                }
            } catch (\Exception $e) {
                $allSuccess = false;
                $messages[] = "$type 解析過程中發生錯誤：" . $e->getMessage();
            }
        }

        if ($allSuccess) {
            return response()->json(['success' => true, 'message' => '所有資料解析成功']);
        } else {
            return response()->json(['success' => false, 'message' => implode("\n", $messages)]);
        }
    } catch (\Exception $e) {
        Log::error("parseAll 錯誤: " . $e->getMessage(), ['id' => $id, 'trace' => $e->getTraceAsString()]);
        return response()->json(['success' => false, 'message' => '解析失敗: ' . $e->getMessage()], 500);
    }
}
}