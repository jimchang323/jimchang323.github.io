<?php

namespace App\Http\Controllers;

use App\Models\CoefficientMain;
use App\Models\ExEmissionCoefficient;
use App\Models\Gas;
use App\Models\Subcategory;
use App\Models\EmissionSourceCategory;
use App\Models\Fuel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Reader\Ods;
use Illuminate\Support\Facades\DB;
class OdsParserController extends Controller
{
    public function list()
    {
        return view('coefficient-main.list', compact('data'));
    }
    // 檢查 CO2 是否已解析
    public function checkParsedCO2($cmId)
    {
        $parsed = ExEmissionCoefficient::where('CM_ID', $cmId)
            ->where('G_id', Gas::where('G_EName', 'CO2')->value('G_id'))
            ->exists();
        return response()->json(['parsed' => $parsed]);
    }
    // 檢查 CH4 是否已解析
    public function checkParsedCH4($cmId)
    {
        $parsed = ExEmissionCoefficient::where('CM_ID', $cmId)
            ->where('G_id', Gas::where('G_EName', 'CH4')->value('G_id'))
            ->exists();
        return response()->json(['parsed' => $parsed]);
    }
    // 檢查 N2O 是否已解析
    public function checkParsedN2O($cmId)
    {
        $parsed = ExEmissionCoefficient::where('CM_ID', $cmId)
            ->where('G_id', Gas::where('G_EName', 'N2O')->value('G_id'))
            ->exists();
        return response()->json(['parsed' => $parsed]);
    }

    private function parseSheet($cmId, Request $request, $gasType, $sheetName, $columnMapping, $recommendationColumn)
    {
        try {
            DB::beginTransaction();

            $coefficientMain = CoefficientMain::find($cmId);
            if (!$coefficientMain) {
                Log::error("無效的 CM_ID: {$cmId}");
                return response()->json(['success' => false, 'message' => '無效的 CM_ID'], 404);
            }

            $enabledGases = Gas::where('G_Status_stop', 1)->pluck('G_id', 'G_EName')->toArray();
            $gId = $enabledGases[strtoupper($gasType)] ?? null;
            if (!$gId) {
                Log::error("{$gasType} 氣體未啟用，CM_ID: {$cmId}");
                return response()->json(['success' => false, 'message' => "{$gasType} 氣體未啟用"], 400);
            }

            $odsFilePath = base_path($coefficientMain->CM_path);
            if (!file_exists($odsFilePath)) {
                Log::error("ODS 檔案不存在: {$odsFilePath}, CM_ID: {$cmId}");
                return response()->json(['success' => false, 'message' => 'ODS 檔案不存在'], 400);
            }

            if (ExEmissionCoefficient::where('CM_ID', $cmId)->where('G_id', $gId)->exists() && !$request->input('force_update', false)) {
                return response()->json(['success' => false, 'message' => "{$gasType} 已解析"]);
            }

            $reader = new Ods();
            $reader->setLoadSheetsOnly([$sheetName]);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($odsFilePath);
            $sheet = $spreadsheet->getSheetByName($sheetName);

            if (!$sheet) {
                Log::error("表單不存在: {$sheetName}, CM_ID: {$cmId}");
                return response()->json(['success' => false, 'message' => "{$gasType} 表單不存在"], 400);
            }

            if ($request->input('force_update', false)) {
                ExEmissionCoefficient::where('CM_ID', $cmId)->where('G_id', $gId)->delete();
            }

            $enabledSubcategories = Subcategory::where('S_Status_stop', 1)->pluck('Sub_id', 'Sub_CName')->toArray();
            $enabledEscCategories = EmissionSourceCategory::where('ESC_Status_stop', 1)->pluck('ESC_id', 'ESC_CName')->toArray();
            $enabledFuels = Fuel::where('F_Status_stop', 1)->pluck('F_id', 'F_CName')->toArray();

            $subRanges = [
                '固定源' => ['start' => 7, 'end' => 53],
                '移動源' => ['start' => 54, 'end' => 61],
            ];

            $escRanges = [
                '固定源' => [
                    '煤' => ['start' => 7, 'end' => 19],
                    '燃料油' => ['start' => 20, 'end' => 35],
                    '燃料氣' => ['start' => 36, 'end' => 40],
                    '其他燃料' => ['start' => 41, 'end' => 43],
                    '生質燃料' => ['start' => 44, 'end' => 53],
                ],
                '移動源' => [
                    '燃料油' => ['start' => 54, 'end' => 61],
                ],
            ];

            $dataToInsert = [];
            $batchSize = 2000;
            $insertedCount = 0;

            foreach ($subRanges as $subName => $subRange) {
                if (!isset($enabledSubcategories[$subName])) {
                    continue;
                }
                $subId = $enabledSubcategories[$subName];

                foreach ($escRanges[$subName] as $escName => $escRange) {
                    if (!isset($enabledEscCategories[$escName])) {
                        //Log::warning("未找到啟用的排放源類別: $escName, 跳過");
                        continue;
                    }
                    $escId = $enabledEscCategories[$escName];

                    for ($fuelRow = $escRange['start']; $fuelRow <= $escRange['end']; $fuelRow++) {
                        $fuelName = $sheet->getCell('D' . $fuelRow)->getValue();
                        if (!isset($enabledFuels[$fuelName])) {
                            continue;
                        }
                        $fId = $enabledFuels[$fuelName];

                        $data = $this->extractData($sheet, $fuelRow, $columnMapping);

                        $coefficient = $data['EX_coefficient'] === '無' ? 0 : floatval($data['EX_coefficient']);
                        $columnOValue = $sheet->getCell($recommendationColumn . $fuelRow)->getValue();
                        $columnOValue = $this->cleanNumericValue($columnOValue);
                        $columnOValue = $columnOValue === '無' ? 0 : floatval($columnOValue);

                        $exRecommendation = (4186.8 * pow(10, -9) * pow(10, -3)) * $coefficient * $columnOValue;
                        $formattedExRecommendation = number_format($exRecommendation, $gasType === 'CO2' ? 4 : 6, '.', '');

                        $dataToInsert[] = [
                            'CM_ID' => (int)$coefficientMain->CM_ID,
                            'G_id' => (int)$gId,
                            'Sub_id' => (int)$subId,
                            'ESC_id' => (int)$escId,
                            'F_id' => (int)$fId,
                            'EX_coefficient' => (string)$data['EX_coefficient'],
                            'EX_recommendation' => (string)$formattedExRecommendation,
                            'EX_Coefficientunit' => (string)$data['EX_Coefficientunit'],
                            'EX_lower_limit' => $data['EX_lower_limit'] === '無' ? 0.0 : (float)$data['EX_lower_limit'],
                            'EX_upper_limit' => $data['EX_upper_limit'] === '無' ? 0.0 : (float)$data['EX_upper_limit'],
                            'EX_Status_stop' => 1
                        ];

                        if (count($dataToInsert) >= $batchSize) {
                            ExEmissionCoefficient::insert($dataToInsert);
                            $insertedCount += count($dataToInsert);
                            $dataToInsert = [];
                        }
                    }
                }
            }

            if (!empty($dataToInsert)) {
                ExEmissionCoefficient::insert($dataToInsert);
                $insertedCount += count($dataToInsert);
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => "{$gasType} 表單解析完成，共插入 {$insertedCount} 條記錄"]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("{$gasType} parse failed for CM_ID {$cmId}: {$e->getMessage()}");
            return response()->json(['success' => false, 'message' => "解析 {$gasType} 表單失敗"], 500);
        }
    }
        // 新增解析 CO2 的方法
        public function parseCO2Sheet($cmId, Request $request)
        {
            $start = microtime(true); // 開始計時
            $columnMapping = [
                5 => 'EX_coefficient',       // F 列
                6 => 'EX_Coefficientunit',   // G 列
                7 => 'EX_lower_limit',       // H 列
                8 => 'EX_upper_limit',       // I 列
            ];
            $end = microtime(true); // 結束計時
            Log::info("CO2 解析耗時: " . ($end - $start) . " 秒, CM_ID: {$cmId}");
            return $this->parseSheet($cmId, $request, 'CO2', '1_固定源與移動源(燃料)CO2排放係數', $columnMapping, 'O');
        }
        // 新增解析 CH4 的方法
        public function parseCH4Sheet($cmId, Request $request)
        {
            $start = microtime(true); // 開始計時
            $columnMapping = [
                2 => 'EX_coefficient',       // C 列
                3 => 'EX_Coefficientunit',   // D 列
                4 => 'EX_lower_limit',       // E 列
                5 => 'EX_upper_limit',       // F 列
            ];
            $end = microtime(true); // 結束計時
            Log::info("CH4 解析耗時: " . ($end - $start) . " 秒, CM_ID: {$cmId}");
            return $this->parseSheet($cmId, $request, 'CH4', '2_固定源與移動源(燃料)CH4排放係數', $columnMapping, 'L');
        }
        // 新增解析 N2O 的方法
        public function parseN2OSheet($cmId, Request $request)
        {
            $start = microtime(true); // 開始計時
            $columnMapping = [
                2 => 'EX_coefficient',       // C 列
                3 => 'EX_Coefficientunit',   // D 列
                4 => 'EX_lower_limit',       // E 列
                5 => 'EX_upper_limit',       // F 列
            ];
            $end = microtime(true); // 結束計時
            Log::info("N2O 解析耗時: " . ($end - $start) . " 秒, CM_ID: {$cmId}");
            return $this->parseSheet($cmId, $request, 'N2O', '3_固定源與移動源(燃料)N2O排放係數', $columnMapping, 'L');
        }
        private function extractData($sheet, $row, $columnMapping)
        {
            $data = [];
            for ($pos = 1; $pos <= 19; $pos++) {
                $column = $this->getColumnByPosition(4 + $pos);
                $value = $sheet->getCell($column . $row)->getValue();
                if (array_key_exists($pos, $columnMapping)) {
                    $field = $columnMapping[$pos];
                    Log::debug("提取資料 - 欄位: {$field}, 位置: {$column}{$row}, 值: " . ($value ?? '空'));
                    if ($field === 'EX_coefficient' || $field === 'EX_recommendation') {
                        $data[$field] = $this->cleanNumericValue($value) ?? '無';
                    } elseif ($field === 'EX_lower_limit' || $field === 'EX_upper_limit') {
                        $data[$field] = $this->parseUncertainty($value) ?? '無';
                    } else {
                        $data[$field] = $value ? trim($value) : '無';
                    }
                }
            }
            return $data;
        }
    private function getColumnByPosition($position)
    {
        $column = '';
        while ($position > 0) {
            $mod = ($position - 1) % 26;
            $column = chr(65 + $mod) . $column;
            $position = (int)(($position - 1) / 26);
        }
        return $column;
    }
    private function cleanNumericValue($value)
    {
        if (empty($value)) return '無';
        $value = preg_replace('/[^\d.eE-]/', '', $value);
        if (is_numeric($value)) {
            return floatval($value);
        }
        return '無';
    }
    private function parseUncertainty($value)
    {
        if (empty($value)) return '無';
        
        // 移除 % 符號和 +- 符號
        $value = str_replace(['+', '-', '%', '±'], '', $value);
        
        if (is_numeric($value)) {
            $numericValue = floatval($value);
            Log::debug('parseUncertainty - is_numeric block - Original value: ' . $value . ', Numeric value: ' . $numericValue);
        
            // 如果數值不等於 0，轉換為百分比
            if ($numericValue != 0) {
                $numericValue *= 100;
                return round($numericValue, 3);
            }
            
            return $numericValue; // 數值=0則直接輸出
        }
        
        return '無';
    }
    
    // 新增顯示 CO2 資料的方法
    public function showCO2($cmId)
    {
        $coefficientMain = CoefficientMain::findOrFail($cmId);
        $co2Data = ExEmissionCoefficient::where('CM_ID', $cmId)
            ->where('G_id', Gas::where('G_EName', 'CO2')->where('G_Status_stop', 1)->value('G_id'))
            ->with(['fuel' => function ($query) {
                $query->select('F_id', 'F_CName');
            }, 'gas', 'subcategory', 'emissionSourceCategory'])
            ->paginate(10); // 添加分頁，每頁 10 筆
        Log::debug('CO2 資料查詢結果', ['co2Data' => $co2Data->toArray()]);
        return view('CoefficientMain.show_co2', compact('coefficientMain', 'co2Data'));
    }
    // 新增顯示 CH4 資料的方法
    public function showCH4($cmId)
    {
        $coefficientMain = CoefficientMain::findOrFail($cmId);
        $ch4Data = ExEmissionCoefficient::where('CM_ID', $cmId)
            ->where('G_id', Gas::where('G_EName', 'CH4')->where('G_Status_stop', 1)->value('G_id'))
            ->with(['fuel' => function ($query) {
                $query->select('F_id', 'F_CName');
            }, 'gas', 'subcategory', 'emissionSourceCategory'])
            ->paginate(10);
        Log::debug('CH4 資料查詢結果', ['ch4Data' => $ch4Data->toArray()]);
        return view('CoefficientMain.show_ch4', compact('coefficientMain', 'ch4Data'));
    }
    // 新增顯示 N2O 資料的方法
    public function showN2O($cmId)
    {
        $coefficientMain = CoefficientMain::findOrFail($cmId);
        $n2oData = ExEmissionCoefficient::where('CM_ID', $cmId)
            ->where('G_id', Gas::where('G_EName', 'N2O')->where('G_Status_stop', 1)->value('G_id'))
            ->with(['fuel' => function ($query) {
                $query->select('F_id', 'F_CName');
            }, 'gas', 'subcategory', 'emissionSourceCategory'])
            ->paginate(10);
        Log::debug('N2O 資料查詢結果', ['n2oData' => $n2oData->toArray()]);
        return view('CoefficientMain.show_n2o', compact('coefficientMain', 'n2oData'));
    }
    public function toggleCO2Status($exId, Request $request)
    {
        $emission = ExEmissionCoefficient::findOrFail($exId);
        $emission->EX_Status_stop = $request->input('EX_Status_stop', 0);
        $emission->save();

        return response()->json(['success' => true, 'message' => '狀態更新成功']);
    }
    // 新增更新 CH4 狀態的方法
    public function toggleCH4Status($exId, Request $request)
    {
        $emission = ExEmissionCoefficient::findOrFail($exId);
        $emission->EX_Status_stop = $request->input('EX_Status_stop', 0);
        $emission->save();
        return response()->json(['success' => true, 'message' => '狀態更新成功']);
    }

    // 新增更新 N2O 狀態的方法
    public function toggleN2OStatus($exId, Request $request)
    {
        $emission = ExEmissionCoefficient::findOrFail($exId);
        $emission->EX_Status_stop = $request->input('EX_Status_stop', 0);
        $emission->save();
        return response()->json(['success' => true, 'message' => '狀態更新成功']);
    }
}