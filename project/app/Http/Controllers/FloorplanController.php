<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\FloorPlan;
use App\Models\Factory_area;
use App\Models\Company;
use Illuminate\Support\Facades\File;

class FloorPlanController extends Controller
{
    /**
     * 顯示平面圖詳細資料頁面，根據登入帳號過濾資料並提供廠區下拉選單
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int|null  $fa_id 可選的廠區序號，用於過濾平面圖資料
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function details(Request $request, $fa_id = null)
    {
        // 從 session 中獲取當前登入帳號
        $account = $request->session()->get('logged_in_account');

        // 如果未登入，重定向到登入頁面並顯示提示
        if (!$account) {
            return redirect()->route('company.login')->with('error', '請先登入');
        }

        // 根據帳號查詢對應的 C_ID
        $company = Company::where('C_Account', $account)->first();

        // 如果未找到公司資料，重定向到登入頁面並顯示提示
        if (!$company) {
            return redirect()->route('company.login')->with('error', '未找到相關公司資料');
        }

        $c_id = $company->C_ID;

        // 權限控制：非公司管理員重定向
        if ($request->session()->get('user_role') !== 'company') {
            return redirect()->route('company.function.management');
        }

        // 查詢 FloorPlan 資料，根據 C_ID 過濾並分頁
        $query = FloorPlan::where('C_ID', $c_id);
        
        // 如果提供了 fa_id，則進一步過濾
        if ($fa_id !== null) {
            $query->where('Fa_ID', $fa_id);
        }

        $floorplans = $query->paginate(5);

        // 獲取與當前公司相關的 Factory_area 資料，用於 Fa_ID 下拉選單
        $factory_areas = Factory_area::where('C_ID', $c_id)->get();

        // 傳遞資料到視圖
        return view('floorplan.details', compact('floorplans', 'factory_areas', 'fa_id'));
    }

    /**
     * 儲存平面圖資料
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            // 從 session 中獲取當前登入帳號
            $account = $request->session()->get('logged_in_account');

            // 如果未登入，返回 JSON 錯誤訊息
            if (!$account) {
                return response()->json(['success' => false, 'message' => '請先登入'], 401);
            }

            // 根據帳號查詢對應的 C_ID 和 C_Name
            $company = Company::where('C_Account', $account)->first();

            // 如果未找到公司資料，返回 JSON 錯誤訊息
            if (!$company) {
                return response()->json(['success' => false, 'message' => '未找到相關公司資料'], 404);
            }

            // 權限控制：僅公司管理員可新增
            if ($request->session()->get('user_role') !== 'company') {
                return response()->json(['success' => false, 'message' => '無權限新增平面圖'], 403);
            }

            $c_id = $company->C_ID;
            $c_name = $company->C_Name;

            // 驗證 Fa_ID 是否屬於當前公司
            $factoryArea = Factory_area::where('C_ID', $c_id)->where('Fa_ID', $request->Fa_ID)->first();
            if (!$factoryArea) {
                return response()->json(['success' => false, 'message' => '無效的公司廠區序號'], 400);
            }

            $fa_id = $request->Fa_ID;

            // 先創建平面圖資料，獲取 FP_ID，但暫時將 FP_Picture 設為空
            $floorPlan = FloorPlan::create([
                'C_ID' => $c_id,
                'Fa_ID' => $request->Fa_ID,
                'FP_Picture' => '', // 暫時設為空，稍後更新
                'FP_Text' => $request->FP_Text,
                'FP_Status_stop' => $request->FP_Status ?? 1, // 預設啟用
            ]);

            // 獲取剛剛創建的 FP_ID
            $fp_id = $floorPlan->FP_ID;

            // 創建以公司名稱命名的資料夾，位於專案根目錄
            $basePath = base_path(); // 獲取專案根目錄路徑，例如 /path/to/your/laravel/project
            $companyFolder = $basePath . '/' . $c_name;

            // 如果公司資料夾不存在，則創建
            if (!File::exists($companyFolder)) {
                File::makeDirectory($companyFolder, 0755, true);
            }

            // 在公司資料夾內創建以 Fa_ID 命名的子資料夾
            $factoryFolder = $companyFolder . '/' . $fa_id;
            if (!File::exists($factoryFolder)) {
                File::makeDirectory($factoryFolder, 0755, true);
            }

            // 處理圖片上傳
            $file = $request->file('FP_Picture');
            // 獲取圖片的副檔名
            $extension = $file->getClientOriginalExtension();
            // 使用 FP_ID 生成新的圖片名稱，例如 floorplan_79.png
            $fileName = "floorplan_{$fp_id}.{$extension}";
            $filePath = $c_name . '/' . $fa_id . '/' . $fileName; // 儲存路徑，例如 C_Name/Fa_ID/floorplan_79.png

            // 將檔案移動到公司資料夾內的廠區子資料夾
            $file->move($factoryFolder, $fileName);

            // 更新 FloorPlan 記錄的 FP_Picture 欄位
            $floorPlan->FP_Picture = $filePath;
            $floorPlan->save();

            // 返回成功訊息和創建的資料
            return response()->json([
                'success' => true,
                'message' => '平面圖已成功新增',
                'data' => $floorPlan
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => '新增失敗：' . $e->getMessage()], 500);
        }
    }

    /**
     * 更新平面圖資料
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        try {
            // 從 session 中獲取當前登入帳號
            $account = $request->session()->get('logged_in_account');

            // 如果未登入，返回 JSON 錯誤訊息
            if (!$account) {
                return response()->json(['success' => false, 'message' => '請先登入'], 401);
            }

            // 根據帳號查詢對應的 C_ID 和 C_Name
            $company = Company::where('C_Account', $account)->first();

            // 如果未找到公司資料，返回 JSON 錯誤訊息
            if (!$company) {
                return response()->json(['success' => false, 'message' => '未找到相關公司資料'], 404);
            }

            // 權限控制：僅公司管理員可更新
            if ($request->session()->get('user_role') !== 'company') {
                return response()->json(['success' => false, 'message' => '無權限更新平面圖'], 403);
            }

            $c_id = $company->C_ID;
            $c_name = $company->C_Name;

            // 查找並驗證 FloorPlan 是否屬於當前公司
            $floorPlan = FloorPlan::where('C_ID', $c_id)->findOrFail($id);

            // 驗證 Fa_ID 是否屬於當前公司
            $factoryArea = Factory_area::where('C_ID', $c_id)->where('Fa_ID', $request->Fa_ID)->first();
            if (!$factoryArea) {
                return response()->json(['success' => false, 'message' => '無效的公司廠區序號'], 400);
            }

            $fa_id = $request->Fa_ID;

            // 如果有新圖片上傳，則更新圖片
            if ($request->hasFile('FP_Picture')) {
                // 刪除舊圖片（如果存在）
                if ($floorPlan->FP_Picture) {
                    $oldFilePath = base_path($floorPlan->FP_Picture);
                    if (File::exists($oldFilePath)) {
                        File::delete($oldFilePath);
                    }
                }

                // 創建以公司名稱命名的資料夾，位於專案根目錄
                $basePath = base_path();
                $companyFolder = $basePath . '/' . $c_name;

                // 如果公司資料夾不存在，則創建
                if (!File::exists($companyFolder)) {
                    File::makeDirectory($companyFolder, 0755, true);
                }

                // 在公司資料夾內創建以 Fa_ID 命名的子資料夾
                $factoryFolder = $companyFolder . '/' . $fa_id;
                if (!File::exists($factoryFolder)) {
                    File::makeDirectory($factoryFolder, 0755, true);
                }

                // 處理圖片上傳
                $file = $request->file('FP_Picture');
                $extension = $file->getClientOriginalExtension();
                $fileName = "floorplan_{$id}.{$extension}";
                $filePath = $c_name . '/' . $fa_id . '/' . $fileName;

                // 將檔案移動到公司資料夾內的廠區子資料夾
                $file->move($factoryFolder, $fileName);

                // 更新 FP_Picture 欄位
                $floorPlan->FP_Picture = $filePath;
            }

            // 更新其他欄位
            $floorPlan->Fa_ID = $request->Fa_ID;
            $floorPlan->FP_Text = $request->FP_Text;
            $floorPlan->FP_Status_stop = $request->FP_Status ?? 1;
            $floorPlan->save();

            // 返回成功訊息和更新的資料
            return response()->json([
                'success' => true,
                'message' => '平面圖已成功更新',
                'data' => $floorPlan
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => '更新失敗：' . $e->getMessage()], 500);
        }
    }

    /**
     * 切換平面圖狀態
     *
     * @param  int  $id
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function toggle($id, Request $request)
    {
        try {
            // 從 session 中獲取當前登入帳號
            $account = $request->session()->get('logged_in_account');

            // 如果未登入，返回 JSON 錯誤訊息
            if (!$account) {
                return response()->json(['success' => false, 'message' => '請先登入'], 401);
            }

            // 根據帳號查詢對應的 C_ID
            $company = Company::where('C_Account', $account)->first();

            // 如果未找到公司資料，返回 JSON 錯誤訊息
            if (!$company) {
                return response()->json(['success' => false, 'message' => '未找到相關公司資料'], 404);
            }

            // 權限控制：僅公司管理員可切換狀態
            if ($request->session()->get('user_role') !== 'company') {
                return response()->json(['success' => false, 'message' => '無權限切換平面圖狀態'], 403);
            }

            $c_id = $company->C_ID;

            // 查找並驗證 FloorPlan 是否屬於當前公司
            $floorPlan = FloorPlan::where('C_ID', $c_id)->findOrFail($id);
            $floorPlan->FP_Status_stop = $request->FP_Status_stop;
            $floorPlan->save();

            return response()->json(['success' => true, 'message' => '狀態更新成功']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => '狀態更新失敗：' . $e->getMessage()], 500);
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
        // 將路徑中的 %20 替換為空格（處理檔案名稱中的空格）
        $path = str_replace('%20', ' ', $path);

        // 構建圖片檔案的完整路徑
        $filePath = base_path($path);

        // 檢查檔案是否存在
        if (!File::exists($filePath)) {
            abort(404, '圖片不存在');
        }

        // 獲取檔案的 MIME 類型
        $mimeType = File::mimeType($filePath);

        // 返回圖片檔案
        return response()->file($filePath, [
            'Content-Type' => $mimeType,
        ]);
    }
}