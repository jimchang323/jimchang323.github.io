<?php

namespace App\Http\Controllers;

use App\Models\Spill;
use App\Models\CoefficientMain;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Ods;
use Illuminate\Support\Facades\Log;

class SpillController extends Controller
{
    public function parseOdsSheet(Request $request, $id)
    {
        $start = microtime(true); // 開始計時
        try {
            $coefficientMain = CoefficientMain::findOrFail($id);
            $filePath = base_path($coefficientMain->CM_path);

            if (!file_exists($filePath)) {
                Log::error("ODS 檔案不存在: {$filePath}, CM_ID: {$id}");
                return response()->json(['success' => false, 'message' => 'ODS 檔案不存在']);
            }

            // 優化：使用 Ods Reader，僅載入指定工作表
            $reader = new Ods();
            $reader->setReadDataOnly(true); // 只讀數據，忽略格式
            $reader->setLoadSheetsOnly(['6_逸散排放源']); // 僅載入指定工作表
            $spreadsheet = $reader->load($filePath);
            $worksheet = $spreadsheet->getSheetByName('6_逸散排放源');

            if (!$worksheet) {
                Log::error("工作表「6_逸散排放源」不存在, CM_ID: {$id}");
                return response()->json(['success' => false, 'message' => '工作表「6_逸散排放源」不存在']);
            }

            $row = 382; // 固定從第 382 行解析
            $bod = (float) $worksheet->getCell('D' . $row)->getValue(); // Sp_BOD
            $sewage = (float) $worksheet->getCell('F' . $row)->getValue(); // Sp_Sewage
            $workingDays = (float) $worksheet->getCell('G' . $row)->getValue(); // Sp_working_days
            $apiece = (float) $worksheet->getCell('H' . $row)->getValue(); // Sp_apiece
            $wastewaterVolume = (float) $worksheet->getCell('I' . $row)->getValue(); // Sp_Wastewater_volume
            $processingEfficiency = (float) $worksheet->getCell('J' . $row)->getValue(); // Sp_Processing_efficiency

            // 計算化糞池處理效率的百分比
            if ($processingEfficiency >= 100) {
                $n = floor($processingEfficiency / 100);
                $remainder = $processingEfficiency % 100;
                $efficiencyPercent = $remainder / 100 + $n; // 例如 205 -> 2.05
            } else {
                $efficiencyPercent = $processingEfficiency / 100; // 例如 85 -> 0.85
            }

            // 計算 Sp_Emission_coefficient
            $emissionCoefficient = $bod * ($sewage / 1000000000) * $workingDays * ($apiece * $wastewaterVolume) * $efficiencyPercent;

            $data = [
                'Sp_Raw_material_name' => (string) $worksheet->getCell('B' . $row)->getValue(),
                'Sp_Device_name' => (string) $worksheet->getCell('C' . $row)->getValue(),
                'Sp_BOD' => $bod,
                'Sp_unit' => (string) $worksheet->getCell('E' . $row)->getValue(),
                'Sp_Sewage' => $sewage,
                'Sp_working_days' => $workingDays,
                'Sp_apiece' => $apiece,
                'Sp_Wastewater_volume' => $wastewaterVolume,
                'Sp_Processing_efficiency' => $processingEfficiency,
                'Sp_Emission_coefficient' => $emissionCoefficient,
                'Sp_unit2' => (string) $worksheet->getCell('L' . $row)->getValue(),
                'CM_ID' => (int) $id,
                'Sp_Status_stop' => 1, // 預設啟用
            ];

            // 檢查是否已存在相同 CM_ID 的記錄，若存在則更新，否則新增
            Spill::updateOrCreate(
                ['CM_ID' => $id],
                $data
            );
            $end = microtime(true); // 結束計時
            Log::info("Spill 解析耗時: " . ($end - $start) . " 秒, CM_ID: {$id}");
           // Log::info("Spill 資料解析成功, CM_ID: {$id}, Emission Coefficient: {$emissionCoefficient}");
            return response()->json(['success' => true, 'message' => '逸散排放源資料解析成功！']);
        } catch (\Exception $e) {
           // Log::error("Spill 解析失敗, CM_ID: {$id}, 錯誤: {$e->getMessage()}");
            return response()->json(['success' => false, 'message' => '解析失敗：' . $e->getMessage()]);
        }
    }
    

    public function show($id)
    {
        $coefficientMain = CoefficientMain::findOrFail($id);
        $spillData = Spill::where('CM_ID', $id)->paginate(10); // 分頁顯示，每頁 10 筆
        foreach ($spillData as $data) {
            $data->Sp_BOD = rtrim(rtrim(number_format($data->Sp_BOD, 10, '.', ''), '0'), '.');
        }
        //dd('Debug: Reaching show method'); // 測試是否進入此方法
        return view('CoefficientMain.show_spill', compact('coefficientMain', 'spillData'));
    }

    public function updateStatus(Request $request, $id)
    {
        $spill = Spill::findOrFail($id);
        $spill->Sp_Status_stop = $request->input('Sp_Status_stop');
        $spill->save();

        return response()->json(['success' => true, 'message' => '狀態更新成功']);
    }

    public function updateEmissionCoefficient(Request $request, $id)
    {
        $spill = Spill::findOrFail($id);

        // 從表單獲取使用者輸入的值
        $bod = $request->input('Sp_BOD');
        $sewage = $request->input('Sp_Sewage');
        $workingDays = $request->input('Sp_working_days');
        $apiece = $request->input('Sp_apiece');
        $wastewaterVolume = $request->input('Sp_Wastewater_volume');
        $processingEfficiency = $request->input('Sp_Processing_efficiency');

        // 計算化糞池處理效率的百分比
        $efficiency = $processingEfficiency;
        if ($efficiency >= 100) {
            $n = floor($efficiency / 100); // 整數部分
            $remainder = $efficiency % 100; // 餘數
            $efficiencyPercent = $remainder / 100 + $n; // 例如 205 -> 2.05
        } else {
            $efficiencyPercent = $efficiency / 100; // 例如 84 -> 0.84
        }

        // 計算 CH4 排放係數
        $emissionCoefficient = $bod * ($sewage/ 1000000000) * $workingDays * ($apiece * $wastewaterVolume) * $efficiencyPercent;

        // 更新資料庫
        $spill->update([
            'Sp_BOD' => $bod,
            'Sp_Sewage' => $sewage,
            'Sp_working_days' => $workingDays,
            'Sp_apiece' => $apiece,
            'Sp_Wastewater_volume' => $wastewaterVolume,
            'Sp_Processing_efficiency' => $processingEfficiency,
            'Sp_Emission_coefficient' => $emissionCoefficient,
        ]);

        return redirect()->route('spill.show', $id)->with('success', 'CH4 排放係數更新成功');
    }
    public function store(Request $request)
    {
    $data = $request->only([
        'Sp_Raw_material_name', 'Sp_Device_name', 'Sp_BOD', 'Sp_unit', 'Sp_Sewage',
        'Sp_working_days', 'Sp_apiece', 'Sp_Wastewater_volume', 'Sp_Processing_efficiency',
        'Sp_unit2', 'Sp_Status_stop', 'CM_ID'
    ]);

    // 計算化糞池處理效率百分比
    $efficiency = $data['Sp_Processing_efficiency'];
    $efficiencyPercent = $efficiency >= 100 ? (floor($efficiency / 100) + ($efficiency % 100) / 100) : $efficiency / 100;

    // 計算 CH4 排放係數
    $data['Sp_Emission_coefficient'] =round( $data['Sp_BOD'] * ($data['Sp_Sewage'] / 1000000000) * 
        $data['Sp_working_days'] * ($data['Sp_apiece'] * $data['Sp_Wastewater_volume']) * $efficiencyPercent,6);
    // 儲存資料
    Spill::create($data);

    return response()->json(['success' => true, 'message' => '資料已創建']);
    }
    public function checkParsed($id)
    {
        $parsed = Spill::where('CM_ID', $id)->exists();
        return response()->json(['parsed' => $parsed]);
    }
    }