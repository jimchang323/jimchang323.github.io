<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\Company;
use App\Models\Factory_Equipment;
use App\Models\Staff;
use App\Models\Category;
use App\Models\Subcategory;
use App\Models\Gas;
use App\Models\EmissionSourceCategory;
use App\Models\Fuel;
use App\Models\ExEmissionCoefficient;
use App\Models\GWPMain;
use App\Models\GWP_Details;
use App\Models\Factory_Equipment_Emission_Sources;
use App\Models\Electricity;
use App\Models\Refrigerant;
use App\Models\DBBoundary;
use App\Models\Main;

class Factory_Equipment_Emission_SourcesController extends Controller
{
    /**
     * 顯示公司廠區排放設備詳細資料，根據登入帳號和 DBB_ID 過濾資料
     */
    public function details(Request $request, $DBB_ID)
    {
        $account = $request->session()->get('logged_in_account');

        if (!$account) {
            return redirect()->route('company.login')->with('error', '請先登入');
        }

        $company = Company::where('C_Account', $account)->first();

        if (!$company) {
            return redirect()->route('company.login')->with('error', '未找到相關公司資料');
        }

        // 驗證 DBB_ID 是否有效
        $dbBoundary = DBBoundary::where('DBB_ID', $DBB_ID)->first();
        if (!$dbBoundary) {
            return redirect()->route('company.login')->with('error', '無效的盤查邊界 ID');
        }

        $emissionSources = Factory_Equipment_Emission_Sources::join('Factory_Equipment', 'Factory_Equipment_Emission_Sources.FE_ID', '=', 'Factory_Equipment.FE_ID')
            ->join('Staff', 'Factory_Equipment_Emission_Sources.S_ID', '=', 'Staff.S_ID')
            ->join('Category', 'Factory_Equipment_Emission_Sources.Cat_id', '=', 'Category.Cat_id')
            ->join('Subcategory', 'Factory_Equipment_Emission_Sources.Sub_id', '=', 'Subcategory.Sub_id')
            ->join('Gas', 'Factory_Equipment_Emission_Sources.G_id', '=', 'Gas.G_id')
            ->join('EmissionSourceCategory', 'Factory_Equipment_Emission_Sources.ESC_id', '=', 'EmissionSourceCategory.ESC_id')
            ->join('Fuel', 'Factory_Equipment_Emission_Sources.F_id', '=', 'Fuel.F_id')
            ->join('EX_Emission_coefficient', 'Factory_Equipment_Emission_Sources.EX_ELE_REF_ID', '=', 'EX_Emission_coefficient.EX_ID')
            ->join('GWP_Details', 'Factory_Equipment_Emission_Sources.GWP', '=', 'GWP_Details.GWP_ID')
            ->where('Factory_Equipment_Emission_Sources.DBB_ID', $DBB_ID)
            ->where('Factory_Equipment_Emission_Sources.flag', 1)
            ->select(
                'Factory_Equipment_Emission_Sources.FEE_id',
                'Factory_Equipment_Emission_Sources.FEE_JUD_ID',
                'Factory_Equipment.FE_Name',
                'Staff.S_Name',
                'Category.Cat_CName',
                'Subcategory.Sub_CName',
                'Gas.G_CName',
                'EmissionSourceCategory.ESC_CName',
                'Fuel.F_CName',
                'EX_Emission_coefficient.EX_recommendation',
                'GWP_Details.GWPD_report4',
                'Factory_Equipment_Emission_Sources.FEE_Status_stop',
                'Category.Cat_id',
                'Subcategory.Sub_id',
                'Gas.G_id',
                'EmissionSourceCategory.ESC_id',
                'Fuel.F_id',
                'EX_Emission_coefficient.EX_ID as EX_ELE_REF_ID',
                'GWP_Details.GWP_ID as GWP',
                'GWP_Details.GWPD_report4'
            )
            ->orderBy('Factory_Equipment_Emission_Sources.FEE_id', 'asc')
            ->paginate(5);

        $factoryEquipments = Factory_Equipment::all(['FE_ID', 'FE_Name']);
        $staff = Staff::all(['S_ID', 'S_Name']);
        $categories = Category::all(['Cat_id', 'Cat_CName']);
        $subcategories = Subcategory::all(['Sub_id', 'Sub_CName']);
        $gases = Gas::all(['G_id', 'G_CName']); // 修改為取得所有氣體資料
        $emissionSourceCategories = EmissionSourceCategory::all(['ESC_id', 'ESC_CName']);
        $fuels = Fuel::all(['F_id', 'F_CName']);
        $electricityYears = Electricity::distinct()->pluck('ELE_coefficient')->sort()->values();

        return view('factory_equipment_emission_sources.details', compact(
            'emissionSources',
            'factoryEquipments',
            'staff',
            'categories',
            'subcategories',
            'gases',
            'emissionSourceCategories',
            'fuels',
            'DBB_ID',
            'electricityYears'
        ));
    }
    /**
     * 獲取限制為氫氟碳化物的氣體名稱
     */
    public function getRestrictedGases(Request $request)
    {
        try {
            $gases = Gas::where('G_CName', '氫氟碳化物')
                ->where('G_Status_stop', 1)
                ->select('G_id', 'G_CName')
                ->get();

            if ($gases->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => '未找到氫氟碳化物氣體資料'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'gases' => $gases
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '查詢失敗：' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * 獲取冷媒物種資料
     */
    public function getRefrigerantSpecies(Request $request)
    {
        $DBB_ID = $request->input('DBB_ID');

        if (!$DBB_ID) {
            return response()->json(['success' => false, 'message' => '請提供盤查邊界 ID'], 400);
        }

        try {
            $m_id = DBBoundary::where('DBB_ID', $DBB_ID)->value('M_ID');
            if (!$m_id) {
                return response()->json([
                    'success' => false,
                    'message' => '未找到對應的 M_ID'
                ], 404);
            }

            $cm_id = Main::where('M_ID', $m_id)->value('CM_ID');
            if (!$cm_id) {
                return response()->json([
                    'success' => false,
                    'message' => '未找到對應的 CM_ID'
                ], 404);
            }

            $species = Refrigerant::where('CM_ID', $cm_id)
                ->select('EFR_ID', 'EFR_name', 'EFR_source')
                ->distinct()
                ->get();

            if ($species->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => '未找到冷媒物種資料'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'species' => $species
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '查詢失敗：' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * 根據冷媒物種查詢排放係數與 GWP
     */
    public function getRefrigerantCoefficient(Request $request)
    {
        $efrId = $request->input('EFR_ID');
        $DBB_ID = $request->input('DBB_ID');

        if (!$efrId || !$DBB_ID) {
            return response()->json(['success' => false, 'message' => '請提供冷媒物種 ID 和盤查邊界 ID'], 400);
        }

        try {
            $refrigerant = Refrigerant::where('EFR_ID', $efrId)->first();

            if (!$refrigerant) {
                return response()->json([
                    'success' => false,
                    'message' => '未找到冷媒資料'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'coefficient' => $refrigerant->EFR_coefficient,
                'gwp' => $refrigerant->EFR_GWP
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '查詢失敗：' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * 顯示冷媒相關資料
     */
    public function refrigerantDetails(Request $request, $DBB_ID)
    {
        $account = $request->session()->get('logged_in_account');

        if (!$account) {
            return redirect()->route('company.login')->with('error', '請先登入');
        }

        $company = Company::where('C_Account', $account)->first();

        if (!$company) {
            return redirect()->route('company.login')->with('error', '未找到相關公司資料');
        }

        $dbBoundary = DBBoundary::where('DBB_ID', $DBB_ID)->first();
        if (!$dbBoundary) {
            return redirect()->route('company.login')->with('error', '無效的盤查邊界 ID');
        }

        $emissionSources = Refrigerant::join('Factory_Equipment', 'Refrigerant.FE_ID', '=', 'Factory_Equipment.FE_ID')
            ->join('Staff', 'Refrigerant.S_ID', '=', 'Staff.S_ID')
            ->join('Category', 'Refrigerant.Cat_id', '=', 'Category.Cat_id')
            ->join('Subcategory', 'Refrigerant.Sub_id', '=', 'Subcategory.Sub_id')
            ->join('Gas', 'Refrigerant.G_id', '=', 'Gas.G_id')
            ->join('EmissionSourceCategory', 'Refrigerant.ESC_id', '=', 'EmissionSourceCategory.ESC_id')
            ->join('Fuel', 'Refrigerant.F_id', '=', 'Fuel.F_id')
            ->join('EX_Emission_coefficient', 'Refrigerant.EX_ELE_REF_ID', '=', 'EX_Emission_coefficient.EX_ID')
            ->join('GWP_Details', 'Refrigerant.GWP', '=', 'GWP_Details.GWP_ID')
            ->where('Refrigerant.DBB_ID', $DBB_ID)
            ->where('Refrigerant.flag', 3)
            ->select(
                'Refrigerant.FEE_id',
                'Refrigerant.FEE_JUD_ID',
                'Factory_Equipment.FE_Name',
                'Staff.S_Name',
                'Category.Cat_CName',
                'Subcategory.Sub_CName',
                'Gas.G_CName',
                'EmissionSourceCategory.ESC_CName',
                'Fuel.F_CName',
                'EX_Emission_coefficient.EX_recommendation',
                'GWP_Details.GWPD_report4',
                'Refrigerant.FEE_Status_stop',
                'Category.Cat_id',
                'Subcategory.Sub_id',
                'Gas.G_id',
                'EmissionSourceCategory.ESC_id',
                'Fuel.F_id',
                'EX_Emission_coefficient.EX_ID as EX_ELE_REF_ID',
                'GWP_Details.GWP_ID as GWP'
            )
            ->orderBy('Refrigerant.FEE_id', 'asc')
            ->paginate(5);

        $factoryEquipments = Factory_Equipment::all(['FE_ID', 'FE_Name']);
        $staff = Staff::all(['S_ID', 'S_Name']);
        $categories = Category::all(['Cat_id', 'Cat_CName']);
        $subcategories = Subcategory::all(['Sub_id', 'Sub_CName']);
        $gases = Gas::whereIn('G_CName', ['二氧化碳', '甲烷', '氧化亞氮'])->get(['G_id', 'G_CName']);
        $emissionSourceCategories = EmissionSourceCategory::all(['ESC_id', 'ESC_CName']);
        $fuels = Fuel::all(['F_id', 'F_CName']);

        return view('factory_equipment_emission_sources.refrigerant_details', compact(
            'emissionSources',
            'factoryEquipments',
            'staff',
            'categories',
            'subcategories',
            'gases',
            'emissionSourceCategories',
            'fuels',
            'DBB_ID'
        ));
    }

    /**
     * 儲存新的排放設備排放源，包含 DBB_ID 和 flag
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

        $request->validate([
            'DBB_ID' => 'required|exists:DBBoundary,DBB_ID',
            'FE_ID' => 'required|exists:Factory_Equipment,FE_ID',
            'S_ID' => 'required|exists:Staff,S_ID',
            'Cat_id' => 'required|exists:Category,Cat_id',
            'Sub_id' => 'required|exists:Subcategory,Sub_id',
            'G_id' => 'required|array',
            'G_id.*' => 'exists:Gas,G_id',
            'ESC_id' => 'required|exists:EmissionSourceCategory,ESC_id',
            'F_id' => 'required|exists:Fuel,F_id',
            'EX_ELE_REF_ID' => 'required|json',
            'GWP' => 'required|json',
            'FEE_Status_stop' => 'nullable|boolean',
            'is_electricity' => 'nullable|boolean',
            'electricity_year' => 'nullable|integer'
        ]);

        try {
            $emissionSources = [];
            $g_ids = $request->input('G_id');
            $ex_ele_ref_ids = json_decode($request->input('EX_ELE_REF_ID'), true);
            $gwp_ids = json_decode($request->input('GWP'), true);
            $feeJudId = time();
            $isElectricity = $request->input('is_electricity', false);

            if ($isElectricity) {
                // 確保僅選擇二氧化碳
                $co2Gas = Gas::where('G_CName', '二氧化碳')->first();
                if (!$co2Gas) {
                    return response()->json([
                        'success' => false,
                        'message' => '未找到二氧化碳氣體資料，請確認 Gas 資料表是否有 G_CName = \'二氧化碳\' 的記錄'
                    ], 404);
                }
                if (count($g_ids) !== 1 || $g_ids[0] != $co2Gas->G_id) {
                    return response()->json([
                        'success' => false,
                        'message' => '電力排放僅允許選擇二氧化碳氣體，請確認前端僅傳遞二氧化碳的 G_id'
                    ], 400);
                }
                $g_ids = [$co2Gas->G_id]; // 限制為二氧化碳

                // 驗證類別為「能源間接排放」
                $category = Category::where('Cat_id', $request->input('Cat_id'))->first();
                if (!$category || $category->Cat_CName !== '能源間接排放') {
                    return response()->json([
                        'success' => false,
                        'message' => '電力排放僅允許選擇「能源間接排放」類別'
                    ], 400);
                }

                // 驗證排放源類別為「電力」
                $esc = EmissionSourceCategory::where('ESC_id', $request->input('ESC_id'))->first();
                if (!$esc || $esc->ESC_CName !== '電力') {
                    return response()->json([
                        'success' => false,
                        'message' => '電力排放僅允許選擇「電力」排放源類別'
                    ], 400);
                }

                // 驗證燃料為「其他電力」
                $fuel = Fuel::where('F_id', $request->input('F_id'))->first();
                if (!$fuel || $fuel->F_CName !== '其他電力') {
                    return response()->json([
                        'success' => false,
                        'message' => '電力排放僅允許選擇「其他電力」燃料'
                    ], 400);
                }
            }

            foreach ($g_ids as $g_id) {
                if (!isset($ex_ele_ref_ids[$g_id]) || !isset($gwp_ids[$g_id])) {
                    return response()->json([
                        'success' => false,
                        'message' => "缺少 G_id {$g_id} 的 EX_ELE_REF_ID 或 GWP"
                    ], 400);
                }

                $emissionSource = Factory_Equipment_Emission_Sources::create([
                    'DBB_ID' => $request->input('DBB_ID'),
                    'FE_ID' => $request->input('FE_ID'),
                    'S_ID' => $request->input('S_ID'),
                    'Cat_id' => $request->input('Cat_id'),
                    'Sub_id' => $request->input('Sub_id'),
                    'G_id' => $g_id,
                    'ESC_id' => $request->input('ESC_id'),
                    'F_id' => $request->input('F_id'),
                    'EX_ELE_REF_ID' => $ex_ele_ref_ids[$g_id],
                    'GWP' => $gwp_ids[$g_id],
                    'FEE_Status_stop' => $request->input('FEE_Status_stop', 1),
                    'FEE_JUD_ID' => $feeJudId,
                    'flag' => 1, // 儲存至 Factory_Equipment_Emission_Sources
                ]);
                $emissionSources[] = $emissionSource;
            }

            return response()->json([
                'success' => true,
                'message' => '排放設備排放源已成功新增！',
                'emissionSources' => $emissionSources
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '排放設備排放源新增失敗：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 儲存冷媒資料
     */
    public function storeRefrigerant(Request $request)
    {
        $account = $request->session()->get('logged_in_account');

        if (!$account) {
            return response()->json(['success' => false, 'message' => '請先登入'], 401);
        }

        $company = Company::where('C_Account', $account)->first();

        if (!$company) {
            return response()->json(['success' => false, 'message' => '未找到相關公司資料'], 404);
        }

        $request->validate([
            'DBB_ID' => 'required|exists:DBBoundary,DBB_ID',
            'FE_ID' => 'required|exists:Factory_Equipment,FE_ID',
            'S_ID' => 'required|exists:Staff,S_ID',
            'Cat_id' => 'required|exists:Category,Cat_id',
            'Sub_id' => 'required|exists:Subcategory,Sub_id',
            'G_id' => 'required|array',
            'G_id.*' => 'exists:Gas,G_id',
            'ESC_id' => 'required|exists:EmissionSourceCategory,ESC_id',
            'F_id' => 'required|exists:Fuel,F_id',
            'EX_ELE_REF_ID' => 'required|json',
            'GWP' => 'required|json',
            'FEE_Status_stop' => 'nullable|boolean',
        ]);

        try {
            $emissionSources = [];
            $g_ids = $request->input('G_id');
            $ex_ele_ref_ids = json_decode($request->input('EX_ELE_REF_ID'), true);
            $gwp_ids = json_decode($request->input('GWP'), true);
            $feeJudId = time();

            foreach ($g_ids as $g_id) {
                if (!isset($ex_ele_ref_ids[$g_id]) || !isset($gwp_ids[$g_id])) {
                    return response()->json([
                        'success' => false,
                        'message' => "缺少 G_id {$g_id} 的 EX_ELE_REF_ID 或 GWP"
                    ], 400);
                }

                $emissionSource = Refrigerant::create([
                    'DBB_ID' => $request->input('DBB_ID'),
                    'FE_ID' => $request->input('FE_ID'),
                    'S_ID' => $request->input('S_ID'),
                    'Cat_id' => $request->input('Cat_id'),
                    'Sub_id' => $request->input('Sub_id'),
                    'G_id' => $g_id,
                    'ESC_id' => $request->input('ESC_id'),
                    'F_id' => $request->input('F_id'),
                    'EX_ELE_REF_ID' => $ex_ele_ref_ids[$g_id],
                    'GWP' => $gwp_ids[$g_id],
                    'FEE_Status_stop' => $request->input('FEE_Status_stop', 1),
                    'FEE_JUD_ID' => $feeJudId,
                    'flag' => 3, // 冷媒
                ]);
                $emissionSources[] = $emissionSource;
            }

            return response()->json([
                'success' => true,
                'message' => '冷媒資料已成功新增！',
                'emissionSources' => $emissionSources
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '冷媒資料新增失敗：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 切換單筆排放設備排放源的啟用/停用狀態
     */
    public function toggle(Request $request, $id)
    {
        $flag = $request->input('flag');
        $status = $request->input('FEE_Status_stop');

        try {
            $record = $flag === 'refrigerant' ? Refrigerant::findOrFail($id) : Factory_Equipment_Emission_Sources::findOrFail($id);
            $record->FEE_Status_stop = $status;
            $record->save();

            return response()->json(['success' => true, 'message' => '狀態更新成功']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => '狀態更新失敗：' . $e->getMessage()], 500);
        }
    }

    /**
     * 根據選擇的 G_id 陣列, Sub_id, ESC_id, F_id, DBB_ID 查詢 EX_recommendation
     */
    public function getExRecommendation(Request $request)
    {
        $g_ids = $request->input('G_id');
        $sub_id = $request->input('Sub_id');
        $esc_id = $request->input('ESC_id');
        $f_id = $request->input('F_id');
        $is_electricity = $request->input('is_electricity', false);
        $electricity_year = $request->input('electricity_year');
        $DBB_ID = $request->input('DBB_ID');

        if (!is_array($g_ids)) {
            $g_ids = [$g_ids];
        }

        try {
            // 查詢 M_ID 和 CM_ID
            $m_id = DBBoundary::where('DBB_ID', $DBB_ID)->value('M_ID');
            if (!$m_id) {
                return response()->json([
                    'success' => false,
                    'message' => '未找到對應的 M_ID'
                ], 404);
            }
            $cm_id = Main::where('M_ID', $m_id)->value('CM_ID');
            if (!$cm_id) {
                return response()->json([
                    'success' => false,
                    'message' => '未找到對應的 CM_ID'
                ], 404);
            }

            $results = [];
            if ($is_electricity) {
                // 確保僅選擇二氧化碳
                $co2Gas = Gas::where('G_CName', '二氧化碳')->first();
                if (!$co2Gas) {
                    return response()->json([
                        'success' => false,
                        'message' => '未找到二氧化碳氣體資料，請確認 Gas 資料表是否有 G_CName = \'二氧化碳\' 的記錄'
                    ], 404);
                }
                if (count($g_ids) !== 1 || $g_ids[0] != $co2Gas->G_id) {
                    return response()->json([
                        'success' => false,
                        'message' => '電力排放僅允許選擇二氧化碳氣體，請確認前端僅傳遞二氧化碳的 G_id'
                    ], 400);
                }
                $g_ids = [$co2Gas->G_id]; // 限制為二氧化碳

                if (!$electricity_year) {
                    return response()->json([
                        'success' => false,
                        'message' => '請選擇電力係數年份'
                    ], 400);
                }

                // 查詢指定年份的電力排放係數
                $electricity = Electricity::where('ELE_coefficient', $electricity_year)
                    ->where('CM_ID', $cm_id)
                    ->whereNotNull('ELE_co2')
                    ->first();

                if ($electricity) {
                    $exCoefficient = ExEmissionCoefficient::where('CM_ID', $cm_id)
                        ->where('G_id', $co2Gas->G_id)
                        ->first();

                    if ($exCoefficient) {
                        $results[] = [
                            'G_id' => $co2Gas->G_id,
                            'G_CName' => $co2Gas->G_CName,
                            'EX_ELE_REF_ID' => $exCoefficient->EX_ID,
                            'EX_recommendation' => $electricity->ELE_co2
                        ];
                    } else {
                        return response()->json([
                            'success' => false,
                            'message' => '未找到對應的排放係數資料，請確認 ExEmissionCoefficient 表'
                        ], 404);
                    }
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => '未找到指定年份的電力係數資料，請確認 Electricity 表'
                    ], 404);
                }
            } else {
                foreach ($g_ids as $g_id) {
                    $exCoefficient = ExEmissionCoefficient::where([
                        'CM_ID' => $cm_id,
                        'G_id' => $g_id,
                        'Sub_id' => $sub_id,
                        'ESC_id' => $esc_id,
                        'F_id' => $f_id
                    ])->first();

                    if ($exCoefficient) {
                        $gas = Gas::where('G_id', $g_id)->first();
                        $results[] = [
                            'G_id' => $g_id,
                            'G_CName' => $gas ? $gas->G_CName : '未知氣體',
                            'EX_ELE_REF_ID' => $exCoefficient->EX_ID,
                            'EX_recommendation' => $exCoefficient->EX_recommendation
                        ];
                    }
                }
            }

            if (!empty($results)) {
                return response()->json([
                    'success' => true,
                    'results' => $results
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => '未找到符合條件的排放係數'
                ], 404);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '查詢失敗：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 根據 G_id 陣列和 EX_ELE_REF_ID, DBB_ID 查詢 GWPD_report4
     */
    public function getGwpReport(Request $request)
    {
        $g_ids = $request->input('G_id');
        $ex_ele_ref_id = $request->input('EX_ELE_REF_ID');
        $is_electricity = $request->input('is_electricity', false);
        $DBB_ID = $request->input('DBB_ID');

        if (!is_array($g_ids)) {
            $g_ids = [$g_ids];
        }

        try {
            // 查詢 M_ID 和 CM_ID
            $m_id = DBBoundary::where('DBB_ID', $DBB_ID)->value('M_ID');
            if (!$m_id) {
                return response()->json([
                    'success' => false,
                    'message' => '未找到對應的 M_ID'
                ], 404);
            }
            $cm_id = Main::where('M_ID', $m_id)->value('CM_ID');
            if (!$cm_id) {
                return response()->json([
                    'success' => false,
                    'message' => '未找到對應的 CM_ID'
                ], 404);
            }

            $exCoefficient = ExEmissionCoefficient::where('EX_ID', $ex_ele_ref_id)->first();
            if (!$exCoefficient) {
                return response()->json([
                    'success' => false,
                    'message' => '未找到排放係數資料，請確認 ExEmissionCoefficient 表'
                ], 404);
            }

            if ($is_electricity) {
                // 確保僅選擇二氧化碳
                $co2Gas = Gas::where('G_CName', '二氧化碳')->first();
                if (!$co2Gas) {
                    return response()->json([
                        'success' => false,
                        'message' => '未找到二氧化碳氣體資料，請確認 Gas 資料表是否有 G_CName = \'二氧化碳\' 的記錄'
                    ], 404);
                }
                if (count($g_ids) !== 1 || $g_ids[0] != $co2Gas->G_id) {
                    return response()->json([
                        'success' => false,
                        'message' => '電力排放僅允許選擇二氧化碳氣體，請確認前端僅傳遞二氧化碳的 G_id'
                    ], 400);
                }
                $g_ids = [$co2Gas->G_id]; // 限制為二氧化碳
            }

            $results = [];
            foreach ($g_ids as $g_id) {
                $gwpMain = GWPMain::where([
                    'G_ID' => $g_id,
                    'CM_ID' => $cm_id
                ])->first();

                if ($gwpMain) {
                    $gwpDetails = GWP_Details::where('GWP_ID', $gwpMain->GWP_ID)->first();
                    if ($gwpDetails) {
                        $gas = Gas::where('G_id', $g_id)->first();
                        $results[] = [
                            'G_id' => $g_id,
                            'G_CName' => $gas ? $gas->G_CName : '未知氣體',
                            'GWP_ID' => $gwpDetails->GWP_ID,
                            'GWPD_report4' => $gwpDetails->GWPD_report4
                        ];
                    }
                }
            }

            if (!empty($results)) {
                return response()->json([
                    'success' => true,
                    'results' => $results
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => '未找到 GWP 詳細資料'
                ], 404);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '查詢失敗：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 根據 Cat_id, Sub_id, ESC_id, F_id, G_id 查詢 EX_Emission_coefficient 和 GWP_Details
     */
    public function getDataBySelections(Request $request)
    {
        $cat_id = $request->input('Cat_id');
        $sub_id = $request->input('Sub_id');
        $esc_id = $request->input('ESC_id');
        $f_id = $request->input('F_id');
        $g_ids = $request->input('G_id');
        $DBB_ID = $request->input('DBB_ID');

        if (!$cat_id || !$sub_id || !$esc_id || !$f_id || empty($g_ids) || !$DBB_ID) {
            return response()->json([
                'success' => false,
                'message' => '請提供完整的類別、子類別、排放源類別、燃料、氣體和盤查邊界 ID'
            ], 400);
        }

        if (!is_array($g_ids)) {
            $g_ids = [$g_ids];
        }

        try {
            // 驗證 DBB_ID
            $dbBoundary = DBBoundary::where('DBB_ID', $DBB_ID)->first();
            if (!$dbBoundary) {
                return response()->json([
                    'success' => false,
                    'message' => '無效的盤查邊界 ID'
                ], 404);
            }

            // 查詢 M_ID 和 CM_ID
            $m_id = DBBoundary::where('DBB_ID', $DBB_ID)->value('M_ID');
            if (!$m_id) {
                return response()->json([
                    'success' => false,
                    'message' => '未找到對應的 M_ID'
                ], 404);
            }
            $cm_id = Main::where('M_ID', $m_id)->value('CM_ID');
            if (!$cm_id) {
                return response()->json([
                    'success' => false,
                    'message' => '未找到對應的 CM_ID'
                ], 404);
            }

            $results = [];
            foreach ($g_ids as $g_id) {
                // 查詢 EX_Emission_coefficient
                $exCoefficient = ExEmissionCoefficient::where([
                    'CM_ID' => $cm_id,
                    'G_id' => $g_id,
                    'Sub_id' => $sub_id,
                    'ESC_id' => $esc_id,
                    'F_id' => $f_id
                ])->first();

                if (!$exCoefficient) {
                    continue; // 如果找不到排放係數，跳過此氣體
                }

                // 查詢 GWP_Details
                $gwpMain = GWPMain::where([
                    'G_ID' => $g_id,
                    'CM_ID' => $cm_id
                ])->first();

                $gwp_report4 = null;
                if ($gwpMain) {
                    $gwpDetails = GWP_Details::where('GWP_ID', $gwpMain->GWP_ID)->first();
                    $gwp_report4 = $gwpDetails ? $gwpDetails->GWPD_report4 : null;
                }

                $gas = Gas::where('G_id', $g_id)->first();
                $category = Category::where('Cat_id', $cat_id)->first();
                $subcategory = Subcategory::where('Sub_id', $sub_id)->first();
                $emissionSourceCategory = EmissionSourceCategory::where('ESC_id', $esc_id)->first();
                $fuel = Fuel::where('F_id', $f_id)->first();

                $results[] = [
                    'G_id' => $g_id,
                    'G_CName' => $gas ? $gas->G_CName : '未知氣體',
                    'Cat_id' => $cat_id,
                    'Cat_CName' => $category ? $category->Cat_CName : '未知類別',
                    'Sub_id' => $sub_id,
                    'Sub_CName' => $subcategory ? $subcategory->Sub_CName : '未知子類別',
                    'ESC_id' => $esc_id,
                    'ESC_CName' => $emissionSourceCategory ? $emissionSourceCategory->ESC_CName : '未知排放源類別',
                    'F_id' => $f_id,
                    'F_CName' => $fuel ? $fuel->F_CName : '未知燃料',
                    'EX_ELE_REF_ID' => $exCoefficient->EX_ID,
                    'EX_recommendation' => $exCoefficient->EX_recommendation,
                    'GWP_ID' => $gwpMain ? $gwpMain->GWP_ID : null,
                    'GWPD_report4' => $gwp_report4
                ];
            }

            if (!empty($results)) {
                return response()->json([
                    'success' => true,
                    'results' => $results
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => '未找到符合條件的資料'
                ], 404);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '查詢失敗：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 根據 DBB_ID 查詢 EX_Emission_coefficient 和 GWP_Details 資料
     */
    public function getEmissionDataByDBBID(Request $request)
    {
        $DBB_ID = $request->input('DBB_ID');

        if (!$DBB_ID) {
            return response()->json([
                'success' => false,
                'message' => '請提供盤查邊界 ID'
            ], 400);
        }

        try {
            // 驗證 DBB_ID
            $dbBoundary = DBBoundary::where('DBB_ID', $DBB_ID)->first();
            if (!$dbBoundary) {
                return response()->json([
                    'success' => false,
                    'message' => '無效的盤查邊界 ID'
                ], 404);
            }

            // 查詢 M_ID 和 CM_ID
            $m_id = DBBoundary::where('DBB_ID', $DBB_ID)->value('M_ID');
            if (!$m_id) {
                return response()->json([
                    'success' => false,
                    'message' => '未找到對應的 M_ID'
                ], 404);
            }
            $cm_id = Main::where('M_ID', $m_id)->value('CM_ID');
            if (!$cm_id) {
                return response()->json([
                    'success' => false,
                    'message' => '未找到對應的 CM_ID'
                ], 404);
            }

            // 查詢 EX_Emission_coefficient 資料
            $exCoefficients = ExEmissionCoefficient::where('CM_ID', $cm_id)
                ->join('Category', 'EX_Emission_coefficient.Cat_id', '=', 'Category.Cat_id')
                ->join('Subcategory', 'EX_Emission_coefficient.Sub_id', '=', 'Subcategory.Sub_id')
                ->join('EmissionSourceCategory', 'EX_Emission_coefficient.ESC_id', '=', 'EmissionSourceCategory.ESC_id')
                ->join('Fuel', 'EX_Emission_coefficient.F_id', '=', 'Fuel.F_id')
                ->join('Gas', 'EX_Emission_coefficient.G_id', '=', 'Gas.G_id')
                ->select(
                    'EX_Emission_coefficient.*',
                    'Category.Cat_CName',
                    'Subcategory.Sub_CName',
                    'EmissionSourceCategory.ESC_CName',
                    'Fuel.F_CName',
                    'Gas.G_CName'
                )
                ->get();

            if ($exCoefficients->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => '未找到對應的排放係數資料'
                ], 404);
            }

            $results = [];
            foreach ($exCoefficients as $exCoefficient) {
                // 查詢 GWP_Details
                $gwpMain = GWPMain::where([
                    'G_ID' => $exCoefficient->G_id,
                    'CM_ID' => $cm_id
                ])->first();

                $gwp_report4 = null;
                if ($gwpMain) {
                    $gwpDetails = GWP_Details::where('GWP_ID', $gwpMain->GWP_ID)->first();
                    $gwp_report4 = $gwpDetails ? $gwpDetails->GWPD_report4 : null;
                }

                $results[] = [
                    'Cat_CName' => $exCoefficient->Cat_CName,
                    'Sub_CName' => $exCoefficient->Sub_CName,
                    'ESC_CName' => $exCoefficient->ESC_CName,
                    'F_CName' => $exCoefficient->F_CName,
                    'G_CName' => $exCoefficient->G_CName,
                    'EX_recommendation' => $exCoefficient->EX_recommendation,
                    'GWPD_report4' => $gwp_report4
                ];
            }

            return response()->json([
                'success' => true,
                'results' => $results
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '查詢失敗：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 根據 Cat_id 獲取對應的 Subcategory 資料
     */
    public function getSubcategoriesByCategory(Request $request)
    {
        $cat_id = $request->input('Cat_id');

        if (!$cat_id) {
            return response()->json(['success' => false, 'message' => '請提供類別 ID'], 400);
        }

        try {
            $subcategories = Subcategory::where('Cat_id', $cat_id)->get(['Sub_id', 'Sub_CName']);
            
            if ($subcategories->isEmpty()) {
                return response()->json(['success' => false, 'message' => '未找到對應的子類別'], 404);
            }

            return response()->json([
                'success' => true,
                'subcategories' => $subcategories
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '查詢失敗：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 根據 Sub_id 從 EX_Emission_coefficient 獲取對應的 EmissionSourceCategory 資料
     */
    public function getEmissionSourceCategoriesBySubcategory(Request $request)
    {
        $sub_id = $request->input('Sub_id');
        $DBB_ID = $request->input('DBB_ID');

        if (!$sub_id || !$DBB_ID) {
            return response()->json(['success' => false, 'message' => '請提供子類別 ID 和盤查邊界 ID'], 400);
        }

        try {
            // 查詢 M_ID
            $m_id = DBBoundary::where('DBB_ID', $DBB_ID)->value('M_ID');
            if (!$m_id) {
                return response()->json([
                    'success' => false,
                    'message' => '未找到對應的 M_ID'
                ], 404);
            }

            $cm_id = Main::where('M_ID', $m_id)->value('CM_ID');
            if (!$cm_id) {
                return response()->json([
                    'success' => false,
                    'message' => '未找到對應的 CM_ID'
                ], 404);
            }

            // 根據 Sub_id 和 CM_ID 查詢 ESC_id
            $escIds = ExEmissionCoefficient::where('Sub_id', $sub_id)
                ->where('CM_ID', $cm_id)
                ->select('ESC_id')
                ->distinct()
                ->get()
                ->pluck('ESC_id');

            if ($escIds->isEmpty()) {
                return response()->json(['success' => false, 'message' => '無法找到對應的排放源類別'], 404);
            }

            // 查詢排放源類別詳細資料
            $emissionSourceCategories = EmissionSourceCategory::whereIn('ESC_id', $escIds)
                ->get(['ESC_id', 'ESC_CName']);

            return response()->json([
                'success' => true,
                'emissionSourceCategories' => $emissionSourceCategories
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '查詢失敗：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 根據 ESC_id 從 EX_Emission_coefficient 中獲取對應的燃料資料
     */
    public function getFuelsByEmissionSourceCategory(Request $request)
    {
        $esc_id = $request->input('ESC_id');
        $DBB_ID = $request->input('DBB_ID');

        if (!$esc_id || !$DBB_ID) {
            return response()->json(['success' => false, 'message' => '請選擇排放源類別 ID 和盤查邊界 ID'], 400);
        }

        try {
            // 查詢 M_ID
            $m_id = DBBoundary::where('DBB_ID', $DBB_ID)->value('M_ID');
            if (!$m_id) {
                return response()->json([
                    'success' => false,
                    'message' => '未找到對應的 M_ID'
                ], 404);
            }

            // 根據 M_ID 查詢對應的 CM_ID
            $cm_id = Main::where('M_ID', $m_id)->value('CM_ID');
            if (!$cm_id) {
                return response()->json([
                    'success' => false,
                    'message' => '未找到對應的 CM_ID'
                ], 404);
            }

            // 根據 ESC_id 和 CM_ID 查詢 F_id
            $fuelIds = ExEmissionCoefficient::where('ESC_id', $esc_id)
                ->where('CM_ID', $cm_id)
                ->select('F_id')
                ->distinct()
                ->get()
                ->pluck('F_id');

            if ($fuelIds->isEmpty()) {
                return response()->json(['success' => false, 'message' => '無法找到對應的燃料'], 404);
            }

            // 查詢燃料詳細資料
            $fuels = Fuel::whereIn('F_id', $fuelIds)->get(['F_id', 'F_CName']);

            return response()->json([
                'success' => true,
                'fuels' => $fuels
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '查詢失敗：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 從 Factory_Equipment_Emission_Sources 表中獲取限制為二氧化碳、甲烷、氧化亞氮的氣體名稱
     */
    public function getRestrictedGasNames(Request $request)
    {
        try {
            $gasNames = Gas::whereIn('G_CName', ['二氧化碳', '甲烷', '氧化亞氮'])
                ->where('G_Status_stop', 1)
                ->select('G_id', 'G_CName')
                ->get();
            if ($gasNames->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => '未找到符合條件的氣體資料（二氧化碳、甲烷、氧化亞氮）'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'gasNames' => $gasNames
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '查詢失敗：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 根據年份查詢電力排放係數
     */
    public function getElectricityCoefficientByYear(Request $request)
    {
        $year = $request->input('year');
        $DBB_ID = $request->input('DBB_ID');

        if (!$year || !$DBB_ID) {
            return response()->json(['success' => false, 'message' => '請提供年份和盤查邊界 ID'], 400);
        }

        try {
            // 查詢 M_ID
            $m_id = DBBoundary::where('DBB_ID', $DBB_ID)->value('M_ID');
            if (!$m_id) {
                return response()->json([
                    'success' => false,
                    'message' => '未找到對應的 M_ID'
                ], 404);
            }

            $cm_id = Main::where('M_ID', $m_id)->value('CM_ID');
            if (!$cm_id) {
                return response()->json([
                    'success' => false,
                    'message' => '未找到對應的 CM_ID'
                ], 404);
            }

            $electricity = Electricity::where('ELE_coefficient', $year)
                ->where('CM_ID', $cm_id)
                ->whereNotNull('ELE_co2')
                ->first();

            if ($electricity) {
                $co2Gas = Gas::where('G_CName', '二氧化碳')->first();
                if (!$co2Gas) {
                    return response()->json([
                        'success' => false,
                        'message' => '無法找到二氧化碳氣體資料，請確認 Gas 資料表是否有 G_CName = \'二氧化碳\' 的記錄'
                    ], 404);
                }

                $exCoefficient = ExEmissionCoefficient::where('CM_ID', $cm_id)
                    ->where('G_id', $co2Gas->G_id)
                    ->first();

                if ($exCoefficient) {
                    return response()->json([
                        'success' => true,
                        'results' => [
                            [
                                'G_id' => $co2Gas->G_id,
                                'G_CName' => $co2Gas->G_CName,
                                'EX_ELE_REF_ID' => $exCoefficient->EX_ID,
                                'EX_recommendation' => $electricity->ELE_co2
                            ]
                        ]
                    ]);
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => '無法找到對應的排放係數資料，請確認 ExEmissionCoefficient 表'
                    ], 404);
                }
            } else {
                return response()->json([
                    'success' => false,
                    'message' => '無法找到指定年份的電力係數資料，請確認 Electricity 表'
                ], 404);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '查詢失敗：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 取得所有電力係數年份
     */
    public function getElectricityYears(Request $request)
    {
        $DBB_ID = $request->input('DBB_ID');

        if (!$DBB_ID) {
            return response()->json(['success' => false, 'message' => '請提供盤查邊界 ID'], 400);
        }

        try {
            // 查詢 M_ID
            $m_id = DBBoundary::where('DBB_ID', $DBB_ID)->value('M_ID');
            if (!$m_id) {
                return response()->json([
                    'success' => false,
                    'message' => '未找到對應的 M_ID'
                ], 404);
            }

            $cm_id = Main::where('M_ID', $m_id)->value('CM_ID');
            if (!$cm_id) {
                return response()->json([
                    'success' => false,
                    'message' => '未找到對應的 CM_ID'
                ], 404);
            }

            $years = Electricity::where('CM_ID', $cm_id)
                ->distinct()
                ->pluck('ELE_coefficient')
                ->sort()
                ->values();

            if ($years->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => '無法找到電力係數年份資料'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'years' => $years
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '查詢失敗：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 儲存電力排放資料
     */
    public function storeElectricity(Request $request)
    {
        $account = $request->session()->get('logged_in_account');

        if (!$account) {
            return response()->json(['success' => false, 'message' => '請先登入'], 401);
        }

        $company = Company::where('C_Account', $account)->first();

        if (!$company) {
            return response()->json(['success' => false, 'message' => '未找到相關公司資料'], 404);
        }

        $request->validate([
            'DBB_ID' => 'required|exists:DBBoundary,DBB_ID',
            'FE_ID' => 'required|exists:Factory_Equipment,FE_ID',
            'S_ID' => 'required|exists:Staff,S_ID',
            'Cat_id' => 'required|exists:Category,Cat_id',
            'Sub_id' => 'required|exists:Subcategory,Sub_id',
            'G_id' => 'required|array',
            'G_id.*' => 'exists:Gas,G_id',
            'ESC_id' => 'required|exists:EmissionSourceCategory,ESC_id',
            'F_id' => 'required|exists:Fuel,F_id',
            'EX_ELE_REF_ID' => 'required|json',
            'GWP' => 'required|json',
            'FEE_Status_stop' => 'nullable|boolean',
            'is_electricity' => 'required|boolean',
            'electricity_year' => 'required|integer'
        ]);

        try {
            $emissionSources = [];
            $g_ids = $request->input('G_id');
            $ex_ele_ref_ids = json_decode($request->input('EX_ELE_REF_ID'), true);
            $gwp_ids = json_decode($request->input('GWP'), true);
            $feeJudId = time();
            $isElectricity = $request->input('is_electricity', true);

            if (!$isElectricity) {
                return response()->json([
                    'success' => false,
                    'message' => '此路由僅用於儲存電力排放資料'
                ], 400);
            }

            // 確保僅選擇二氧化碳
            $co2Gas = Gas::where('G_CName', '二氧化碳')->first();
            if (!$co2Gas) {
                return response()->json([
                    'success' => false,
                    'message' => '未找到二氧化碳氣體資料，請確認 Gas 資料表是否有 G_CName = \'二氧化碳\' 的記錄'
                ], 404);
            }
            if (count($g_ids) !== 1 || $g_ids[0] != $co2Gas->G_id) {
                return response()->json([
                    'success' => false,
                    'message' => '電力排放僅允許選擇二氧化碳氣體，請確認前端僅傳遞二氧化碳的 G_id'
                ], 400);
            }
            $g_ids = [$co2Gas->G_id]; // 限制為二氧化碳

            // 驗證類別為「能源間接排放」
            $category = Category::where('Cat_id', $request->input('Cat_id'))->first();
            if (!$category || $category->Cat_CName !== '能源間接排放') {
                return response()->json([
                    'success' => false,
                    'message' => '電力排放僅允許選擇「能源間接排放」類別'
                ], 400);
            }

            // 驗證排放源類別為「電力」
            $esc = EmissionSourceCategory::where('ESC_id', $request->input('ESC_id'))->first();
            if (!$esc || $esc->ESC_CName !== '電力') {
                return response()->json([
                    'success' => false,
                    'message' => '電力排放僅允許選擇「電力」排放源類別'
                ], 400);
            }

            // 驗證燃料為「其他電力」
            $fuel = Fuel::where('F_id', $request->input('F_id'))->first();
            if (!$fuel || $fuel->F_CName !== '其他電力') {
                return response()->json([
                    'success' => false,
                    'message' => '電力排放僅允許選擇「其他電力」燃料'
                ], 400);
            }

            foreach ($g_ids as $g_id) {
                if (!isset($ex_ele_ref_ids[$g_id]) || !isset($gwp_ids[$g_id])) {
                    return response()->json([
                        'success' => false,
                        'message' => "缺少 G_id {$g_id} 的 EX_ELE_REF_ID 或 GWP"
                    ], 400);
                }

                $emissionSource = Factory_Equipment_Emission_Sources::create([
                    'DBB_ID' => $request->input('DBB_ID'),
                    'FE_ID' => $request->input('FE_ID'),
                    'S_ID' => $request->input('S_ID'),
                    'Cat_id' => $request->input('Cat_id'),
                    'Sub_id' => $request->input('Sub_id'),
                    'G_id' => $g_id,
                    'ESC_id' => $request->input('ESC_id'),
                    'F_id' => $request->input('F_id'),
                    'EX_ELE_REF_ID' => $ex_ele_ref_ids[$g_id],
                    'GWP' => $gwp_ids[$g_id],
                    'FEE_Status_stop' => $request->input('FEE_Status_stop', 1),
                    'FEE_JUD_ID' => $feeJudId,
                    'flag' => 2, // 儲存至 Factory_Equipment_Emission_Sources
                ]);
                $emissionSources[] = $emissionSource;
            }

            return response()->json([
                'success' => true,
                'message' => '電力排放資料已成功新增！',
                'emissionSources' => $emissionSources
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '電力排放資料新增失敗：' . $e->getMessage()
            ], 500);
        }
    }
}