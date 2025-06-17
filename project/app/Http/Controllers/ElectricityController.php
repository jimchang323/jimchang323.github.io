<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Electricity;
use App\Models\CoefficientMain;
use PhpOffice\PhpSpreadsheet\Reader\Ods;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ElectricityController extends Controller
{
    public function parseElectricity(Request $request, $id)
    {
        $start = microtime(true); // 開始計時
        try {
            $coefficientMain = CoefficientMain::findOrFail($id);
            $filePath = base_path($coefficientMain->CM_path);

            if (!file_exists($filePath)) {
                return redirect()->route('coefficient-main.list')->with('error', 'ODS 檔案不存在');
            }

            // 優化：僅載入指定工作表
            $reader = new Ods();
            $reader->setReadDataOnly(true);
            $reader->setLoadSheetsOnly(['5_外購電力與外購蒸汽排放係數']);
            $spreadsheet = $reader->load($filePath);
            $worksheet = $spreadsheet->getSheetByName('5_外購電力與外購蒸汽排放係數');

            if (!$worksheet) {
                return redirect()->route('coefficient-main.list')->with('error', '找不到指定的工作表');
            }

            $highestRow = $worksheet->getHighestRow();
            $rows = $worksheet->rangeToArray('A5:H22' . $highestRow, null, true, false, false);

            if (empty($rows)) {
                return redirect()->route('coefficient-main.list')->with('error', '檔案內沒有資料');
            }

            // 過濾無效行（檢查 A, B, F, G, H 欄是否都有資料）
            $filteredRows = array_filter($rows, function ($row) {
                // 檢查 B 欄是否只是「電力」
            if (stripos($row[1], '電力') === false) {
                return false;
            }
                return !empty($row[0])  && !empty($row[5]) && !empty($row[6]) && !empty($row[7]);
            });

            if (empty($filteredRows)) {
                return redirect()->route('coefficient-main.list')->with('error', '檔案內沒有有效資料');
            }

            // 開始事務
            DB::beginTransaction();

            // 檢查是否已有資料，若有則刪除
            $existingElectricity = Electricity::where('CM_ID', $id)->exists();
            if ($existingElectricity) {
                Electricity::where('CM_ID', $id)->delete();
            }

            // 準備批量插入 Electricity 資料
            $electricityToInsert = [];
            foreach ($filteredRows as $row) {
                $electricityToInsert[] = [
                    'CM_ID' => (int) $coefficientMain->CM_ID,
                    'ELE_coefficient' => (float) $row[0], // A 列：排放係數
                    'ELE_name' => (string) $row[1],       // B 列：名稱
                    'ELE_co2' => (float) $row[5],         // F 列：CO2 排放係數
                    'ELE_unit' => (string) $row[6],       // G 列：單位
                    'ELE_source' => (string) $row[7],     // H 列：來源
                    'ELE_Status_stop' => 1,               // 預設啟用
                ];
            }

            // 批量插入 Electricity 資料
            if (!empty($electricityToInsert)) {
                Electricity::insert($electricityToInsert);
            } else {
                DB::rollBack();
                return redirect()->route('coefficient-main.list')->with('error', '未找到任何有效電力資料');
            }

            DB::commit();
            $end = microtime(true); // 結束計時
            Log::info("電力排放係數解析耗時: " . ($end - $start) . " 秒, CM_ID: {$id}");
            return redirect()->route('electricity.view', $id)->with('success', '電力排放係數資料成功解析');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->route('coefficient-main.list')->with('error', '解析失敗: ' . $e->getMessage());
        }
    }

public function viewElectricity($id, Request $request)
{
    $coefficientMain = CoefficientMain::findOrFail($id);
    $query = Electricity::where('CM_ID', $id);

    // 獲取搜尋參數
    $searchField = $request->input('search_field');
    $searchQuery = $request->input('search_query');
    $statusFilter = $request->input('status_filter', 'all'); // 預設為 'all'
    // 狀態篩選
    if ($statusFilter !== 'all') {
        $query->where('ELE_Status_stop', $statusFilter);
    }
    if ($searchQuery) {
        if ($searchField) {
            // 特定欄位查詢
            $query->where($searchField, 'like', "%{$searchQuery}%");
        } else {
            // 所有欄位查詢
            $query->where(function ($q) use ($searchQuery) {
                $q->where('ELE_coefficient', 'like', "%{$searchQuery}%")
                  ->orWhere('ELE_name', 'like', "%{$searchQuery}%")
                  ->orWhere('ELE_co2', 'like', "%{$searchQuery}%")
                  ->orWhere('ELE_unit', 'like', "%{$searchQuery}%")
                  ->orWhere('ELE_source', 'like', "%{$searchQuery}%");
            });
        }
    }

    // 排序邏輯
    $sortField = $request->input('sort_field');
    $sortDirection = $request->input('sort_direction', 'asc'); // 預設升序
    if ($sortField && in_array($sortField, ['ELE_coefficient', 'ELE_co2'])) {
        $query->orderBy($sortField, $sortDirection);
    }

    $electricities = $query->paginate(10);

    // 保留搜尋和排序參數
    if ($searchField || $searchQuery || $sortField) {
        $electricities->appends([
            'search_field' => $searchField,
            'search_query' => $searchQuery,
            'status_filter' => $statusFilter,
            'sort_field' => $sortField,
            'sort_direction' => $sortDirection,
        ]);
    }

    return view('Electricity.electricity_list', compact('electricities', 'coefficientMain'));
}

    public function toggleStatus(Request $request, $id)
    {
        $electricity = Electricity::findOrFail($id);
        $electricity->ELE_Status_stop = $request->input('status') === '啟用' ? 1 : 0;
        $electricity->save();

        return response()->json(['success' => true, 'message' => '狀態更新成功']);
    }
}