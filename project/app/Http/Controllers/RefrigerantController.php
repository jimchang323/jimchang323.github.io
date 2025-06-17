<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Refrigerant;
use App\Models\CoefficientMain;
use PhpOffice\PhpSpreadsheet\Reader\Ods;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class RefrigerantController extends Controller
{
    public function parseRefrigerant(Request $request, $id)
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
            $reader->setLoadSheetsOnly(['6_逸散排放源']);
            $spreadsheet = $reader->load($filePath);
            $worksheet = $spreadsheet->getSheetByName('6_逸散排放源');

            if (!$worksheet) {
                return redirect()->route('coefficient-main.list')->with('error', '找不到指定的工作表');
            }

            // 限制範圍到 A260:G284
            $rows = $worksheet->rangeToArray('A260:G284', null, true, false, false);
                    Log::info("解析的原始資料行數: " . count($rows));
                    Log::info("原始資料: ", $rows);
            if (empty($rows)) {
                return redirect()->route('coefficient-main.list')->with('error', '檔案內沒有資料');
            }

            // 過濾無效行（檢查 A, E, F, G 欄是否都有資料）
            $filteredRows = array_filter($rows, function ($row) {
                // A-D 欄合併後至少 A 欄要有資料
                if (empty($row[0])) {
                    return false;
                }
                return !empty($row[6]) && !empty($row[4])&& !empty($row[5]);
            });
            Log::info("過濾後的資料行數: " . count($filteredRows));
            Log::info("過濾後的資料: ", $filteredRows);

            if (empty($filteredRows)) {
                return redirect()->route('coefficient-main.list')->with('error', '檔案內沒有有效冷媒資料');
            }

            // 開始事務
            DB::beginTransaction();

            // 檢查是否已有資料，若有則刪除
            $existingRefrigerant = Refrigerant::where('CM_ID', $id)->exists();
            if ($existingRefrigerant) {
                Refrigerant::where('CM_ID', $id)->delete();
            }

            // 準備批量插入 Refrigerant 資料
            $refrigerantToInsert = [];
            foreach ($filteredRows as $row) {
                // 合併 A-D 欄（REF_name）
                $refName = trim(implode(' ', array_filter([$row[0], $row[1], $row[2], $row[3]])));

                $refrigerantToInsert[] = [
                    'CM_ID' => (int) $coefficientMain->CM_ID,
                    'REF_name' => (string) $refName,      // A-D 欄：使用物種
                    'REF_coefficient' => (float) $row[4], // E 欄：排放係數
                    'REF_GWP' => (float) $row[5],         // F 欄：GWP 數值
                    'REF_source' => (string) $row[6],     // G 欄：來源
                    'REF_Status_stop' => 1,               // 預設啟用
                ];
            }

            // 批量插入 Refrigerant 資料
            if (!empty($refrigerantToInsert)) {
                Refrigerant::insert($refrigerantToInsert);
                Log::info("成功插入冷媒資料，行數: " . count($refrigerantToInsert));
            } else {
                DB::rollBack();
                Log::error("未找到任何有效冷媒資料");
                return redirect()->route('coefficient-main.list')->with('error', '未找到任何有效冷媒資料');
            }

            DB::commit();
            $end = microtime(true); // 結束計時
            Log::info("冷媒排放係數解析耗時: " . ($end - $start) . " 秒, CM_ID: {$id}");
            return redirect()->route('refrigerant.view', $id)->with('success', '冷媒資料成功解析');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("解析冷媒失敗: " . $e->getMessage());
            return redirect()->route('coefficient-main.list')->with('error', '解析失敗: ' . $e->getMessage());
        }
    }
    public function viewRefrigerant($id, Request $request)
    {
        $coefficientMain = CoefficientMain::findOrFail($id);
        $query = Refrigerant::where('CM_ID', $id);

        // 獲取搜尋參數
        $searchField = $request->input('search_field');
        $searchQuery = $request->input('search_query');
        $statusFilter = $request->input('status_filter', 'all'); // 預設為 'all'

        // 狀態篩選
        if ($statusFilter !== 'all') {
            $query->where('REF_Status_stop', $statusFilter);
        }

        // 搜尋邏輯
        if ($searchQuery) {
            if ($searchField) {
                // 特定欄位查詢
                $query->where($searchField, 'like', "%{$searchQuery}%");
            } else {
                // 所有欄位查詢
                $query->where(function ($q) use ($searchQuery) {
                    $q->where('REF_name', 'like', "%{$searchQuery}%")
                    ->orWhere('REF_coefficient', 'like', "%{$searchQuery}%")
                    ->orWhere('REF_GWP', 'like', "%{$searchQuery}%")
                    ->orWhere('REF_source', 'like', "%{$searchQuery}%");
                });
            }
        }

        // 排序邏輯
        $sortField = $request->input('sort_field');
        $sortDirection = $request->input('sort_direction', 'asc'); // 預設升序
        if ($sortField && in_array($sortField, ['REF_coefficient', 'REF_GWP'])) {
            $query->orderBy($sortField, $sortDirection);
        }

        // 分頁
        $refrigerants = $query->paginate(10);

        // 保留搜尋和排序參數
        if ($searchField || $searchQuery || $sortField) {
            $refrigerants->appends([
                'search_field' => $searchField,
                'search_query' => $searchQuery,
                'status_filter' => $statusFilter,
                'sort_field' => $sortField,
                'sort_direction' => $sortDirection,
            ]);
        }

        // 記錄日誌
        Log::info("查詢冷媒資料，CM_ID: {$id}, 找到資料數: " . $refrigerants->count());

        return view('Refrigerant.Refrigerant_list', compact('refrigerants', 'coefficientMain'));
    }

    public function toggleStatus(Request $request, $id)
    {
        $refrigerant = Refrigerant::findOrFail($id);
        $refrigerant->REF_Status_stop = $request->input('status') === '啟用' ? 1 : 0;
        $refrigerant->save();

        return response()->json(['success' => true, 'message' => '狀態更新成功']);
    }
}