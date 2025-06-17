<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\GWPMain;
use App\Models\GWP_Details;
use App\Models\CoefficientMain;
use App\Models\Gas;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Reader\Ods;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class GWPController extends Controller
{
    public function parseODS(Request $request, $id)
    {
        $start = microtime(true); // 開始計時
        try {
            $coefficientMain = CoefficientMain::findOrFail($id);
            $filePath = base_path($coefficientMain->CM_path);

            if (!file_exists($filePath)) {
                //Log::error("ODS 檔案不存在: {$filePath}, CM_ID: {$id}");
                return response()->json(['success' => false, 'message' => 'ODS 檔案不存在']);
            }

           // 優化：僅載入指定工作表
           $reader = new Ods();
           $reader->setReadDataOnly(true);
           $reader->setLoadSheetsOnly(['4_含氟氣體之GWP值']);
           $spreadsheet = $reader->load($filePath);
           $worksheet = $spreadsheet->getSheetByName('4_含氟氣體之GWP值');

           if (!$worksheet) {
               //Log::error("工作表「4_含氟氣體之GWP值」不存在, CM_ID: {$id}");
               return response()->json(['success' => false, 'message' => '找不到指定的工作表']);
           }

           $highestRow = $worksheet->getHighestRow();
           $rows = $worksheet->rangeToArray('A3:F' . $highestRow, null, true, false, false);

           if (empty($rows)) {
               //Log::warning("檔案內沒有資料, CM_ID: {$id}");
               return response()->json(['success' => false, 'message' => '檔案內沒有資料']);
           }

           // 提前過濾無效行
           $filteredRows = array_filter($rows, function ($row) {
               return !empty($row[0]) && !empty($row[1]) && !empty($row[2]);
           });

           if (empty($filteredRows)) {
              // Log::warning("無有效資料行, CM_ID: {$id}");
               return response()->json(['success' => false, 'message' => '檔案內沒有有效資料']);
           }

           // 預載啟用的 Gas 記錄
           $enabledGases = Gas::where('G_Status_stop', 1)->get()->keyBy('G_id');
           $gwpMap = []; // G_id 到 GWP_ID 的映射
           $processedGases = []; // 已處理的氣體
           $detailsToInsert = []; // 批量插入 GWP_Details

           // 檢查是否已有資料，若有則刪除
           DB::beginTransaction();
           $existingGwpMain = GWPMain::where('CM_ID', $id)->exists();
           if ($existingGwpMain) {
               GWP_Details::where('CM_ID', $id)->delete();
               GWPMain::where('CM_ID', $id)->delete();
           }

           // 為每個啟用氣體尋找第一個匹配的行
           foreach ($enabledGases as $gas) {
               $matchedRow = null;

               foreach ($filteredRows as $row) {
                   $gwpd_formula = trim($row[1]);
                   if (str_contains($gwpd_formula, $gas->G_CName) || ($gas->G_EName && str_contains($gwpd_formula, $gas->G_EName))) {
                       $matchedRow = $row;
                       break; // 找到第一個匹配的行
                   }
               }

               if ($matchedRow) {
                   // 創建 GWPMain 記錄
                   if (!isset($gwpMap[$gas->G_id])) {
                       $gwpMain = GWPMain::create([
                           'CM_ID' => (int) $coefficientMain->CM_ID,
                           'G_id' => (int) $gas->G_id,
                       ]);
                       $gwpMap[$gas->G_id] = $gwpMain->GWP_ID;
                   }

                   // 準備批量插入 GWP_Details
                   $detailsToInsert[] = [
                       'CM_ID' => (int) $coefficientMain->CM_ID,
                       'GWP_ID' => (int) $gwpMap[$gas->G_id],
                       'GWPD_code' => (string) $matchedRow[0],
                       'GWPD_formula' => (string) $matchedRow[1],
                       'GWPD_report1' => (string) $matchedRow[2],
                       'GWPD_report2' => (string) $matchedRow[3],
                       'GWPD_report3' => (string) $matchedRow[4],
                       'GWPD_report4' => (string) $matchedRow[5],
                       'GWPD_Status_stop' => 1,
                   ];

                   $processedGases[$gas->G_id] = true;
               }
           }

           // 批量插入 GWP_Details
           if (!empty($detailsToInsert)) {
               GWP_Details::insert($detailsToInsert);
           }

           if (empty($processedGases)) {
               DB::rollBack();
               //Log::warning("未找到任何匹配的氣體資料, CM_ID: {$id}");
               return response()->json(['success' => false, 'message' => '未找到任何匹配的氣體資料']);
           }

           DB::commit();
           $end = microtime(true); // 結束計時
        Log::info("GWP 解析耗時: " . ($end - $start) . " 秒, CM_ID: {$id}");
           //Log::info("GWP 資料解析成功, CM_ID: {$id}, 插入記錄數: " . count($detailsToInsert));
           return response()->json(['success' => true, 'message' => '資料成功解析']);
       } catch (\Exception $e) {
           DB::rollBack();
           //Log::error("GWP 解析失敗, CM_ID: {$id}, 錯誤: {$e->getMessage()}");
           return response()->json(['success' => false, 'message' => '解析失敗: ' . $e->getMessage()]);
       }
   }

    public function showGWPDetails($id)
    {
        $coefficientMain = CoefficientMain::findOrFail($id); // 查詢 CoefficientMain
        // 顯示 GWPMain 和 GWP_Details 資料
        $gwpMain = GWPMain::where('CM_ID', $id)->get();
        $gwpDetails = GWP_Details::where('CM_ID', $id)->paginate(10);
        // dd($gwpMain, $gwpDetails);  // 測試資料是否正確查詢到
        // 查詢氣體名稱
        // 預載氣體名稱，避免 N+1 問題
        $gasIds = $gwpMain->pluck('G_id')->unique();
        $gases = Gas::whereIn('G_id', $gasIds)->pluck('G_CName', 'G_id');
        $gasNames = [];
        foreach ($gwpMain as $main) {
            $gasNames[$main->GWP_ID] = $gases[$main->G_id] ?? '未找到氣體名稱';
        }

        return view('gwp.details', compact('gwpMain', 'gwpDetails', 'gasNames', 'coefficientMain'));
   }
    public function showGWPMain($id)
    {
        $gwpMain = GWPMain::where('CM_ID', $id)->paginate(10);
       // 查詢氣體名稱
        $gasIds = $gwpMain->pluck('G_id')->unique();
        $gases = Gas::whereIn('G_id', $gasIds)->get()->keyBy('G_id');
        $gasNames = [];
        foreach ($gwpMain as $main) {
            $gas = $gases[$main->G_id] ?? null;
            if ($gas) {
                $combinedName = $gas->G_CName . ' ' . $gas->G_EName;
                $gasNames[$main->G_id] = $combinedName;
            } else {
                $gasNames[$main->G_id] = '未找到氣體名稱';
            }
        }

        return view('gwp.main', compact('gwpMain', 'gasNames'));
    }
    public function checkParsed($id)
    {
        $parsed = GWPMain::where('CM_ID', $id)->exists();
        return response()->json(['parsed' => $parsed]);
    }
}
