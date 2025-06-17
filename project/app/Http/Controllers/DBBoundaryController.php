<?php

namespace App\Http\Controllers;

use App\Models\DBBoundary;
use App\Models\Main;
use App\Models\Factory_area;
use App\Models\Staff;
use App\Models\FloorPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DBBoundaryController extends Controller
{
    // 列出特定 M_ID 的邊界資料
    public function list(Request $request, $mId)
    {
        $main = Main::with('company')->findOrFail($mId);
        $query = DBBoundary::with(['Factory_area', 'Staff', 'Main'])->where('M_ID', $mId);
        $searchField = $request->input('search_field');
        $searchQuery = $request->input('search_query');

        if ($searchField && $searchQuery) {
            if ($searchField == 'F_Name') {
                $query->whereHas('Factory_area', function ($q) use ($searchQuery) {
                    $q->where('F_Name', 'like', "%{$searchQuery}%");
                });
            } elseif ($searchField == 'S_Name') {
                $query->whereHas('Staff', function ($q) use ($searchQuery) {
                    $q->where('S_Name', 'like', "%{$searchQuery}%");
                });
            } elseif ($searchField == 'DBB_Status_stop') {
                $status = $searchQuery == '啟用' ? 1: ($searchQuery == '停用' ? 0 : null);
                if ($status !== null) {
                    $query->where('DBB_Status_stop', $status);
                }
            }
        }

        $boundaries = $query->paginate(10);
        return view('DBBoundary.db_boundary_list', compact('boundaries', 'main'));
    }

    // 顯示創建表單，接受 M_ID
    public function create($mId)
    {
        $main = Main::with('company')->findOrFail($mId);
        $factoryAreas = Factory_area::all();
        return view('DBBoundary.db_boundary', compact('factoryAreas', 'main'));
    }

    // 顯示邊界設定頁面
    public function index(Request $request, $mId)
    {
        $main = Main::with('company')->findOrFail($mId);
        $existingBoundary = DBBoundary::where('M_ID', $mId)->first();
        $factoryAreas = Factory_area::all();
        return view('DBBoundary.db_boundary', compact('main',  'existingBoundary', 'factoryAreas'));
    }
    public function getStaffsByFactoryArea(Request $request, $faId)
    {
        $staffs = Staff::where('Fa_ID', $faId)->get(['S_ID', 'S_Name']);
        return response()->json(['staffs' => $staffs]);
    }
    // 獲取指定廠區下的平面圖資料
    public function getFloorPlansByFactoryArea(Request $request, $faId)
    {
        $floorPlans = FloorPlan::where('Fa_ID', $faId)
            ->where('FP_Status_stop', 1) // 只顯示啟用的平面圖
            ->get(['FP_ID', 'FP_Picture']);
        
        return response()->json(['floorPlans' => $floorPlans]);
    }
    // 顯示編輯表單
    public function edit($mId, $faId)
    {
        $main = Main::with('company')->findOrFail($mId);
        $existingBoundary = DBBoundary::with(['Factory_area', 'Staff'])->where('M_ID', $mId)->where('Fa_ID', $faId)->firstOrFail();
        $factoryAreas = Factory_area::all();
        return view('DBBoundary.db_boundary', compact('main', 'existingBoundary', 'factoryAreas'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'M_ID' => 'required|exists:main,M_ID',
            'Fa_ID' => 'required|exists:factory_area,Fa_ID',
            'S_ID' => 'required|exists:staff,S_ID',
            'DBB_Plan1' => 'required|exists:Floor_Plan,FP_ID',
            'DBB_Plan2' => 'nullable|exists:Floor_Plan,FP_ID',
            'DBB_Plan3' => 'nullable|exists:Floor_Plan,FP_ID',
            'DBB_Plan4' => 'nullable|exists:Floor_Plan,FP_ID',
            'DBB_Status_stop' => 'required|in:啟用,停用',
        ]);

        $mId = $request->input('M_ID');
        $faId = $request->input('Fa_ID');

        DBBoundary::updateOrCreate(
            ['M_ID' => $mId, 'Fa_ID' => $faId],
            [
                'S_ID' => $request->input('S_ID'),
                'DBB_Plan1' => $request->input('DBB_Plan1'),
                'DBB_Plan2' => $request->input('DBB_Plan2'),
                'DBB_Plan3' => $request->input('DBB_Plan3'),
                'DBB_Plan4' => $request->input('DBB_Plan4'),
                'DBB_Status_stop' => $request->input('DBB_Status_stop') == '啟用' ? 1 : 0,
            ]
        );

        return redirect()->route('db-boundary.list', ['mId' => $mId])
            ->with('success', '邊界設定創建成功');
    }

public function update(Request $request, $mId, $faId)
    {
        $request->validate([
            'S_ID' => 'required|exists:staff,S_ID',
            'DBB_Plan1' => 'required|exists:Floor_Plan,FP_ID',
            'DBB_Plan2' => 'nullable|exists:Floor_Plan,FP_ID',
            'DBB_Plan3' => 'nullable|exists:Floor_Plan,FP_ID',
            'DBB_Plan4' => 'nullable|exists:Floor_Plan,FP_ID',
            'DBB_Status_stop' => 'required|in:啟用,停用',
        ]);

        $boundary = DBBoundary::where('M_ID', $mId)->where('Fa_ID', $faId)->firstOrFail();

        $boundary->update([
            'S_ID' => $request->input('S_ID'),
            'DBB_Plan1' => $request->input('DBB_Plan1'),
            'DBB_Plan2' => $request->input('DBB_Plan2'),
            'DBB_Plan3' => $request->input('DBB_Plan3'),
            'DBB_Plan4' => $request->input('DBB_Plan4'),
            'DBB_Status_stop' => $request->input('DBB_Status_stop') == '啟用' ? 1 : 0,
        ]);

        return response()->json(['message' => '邊界設定更新成功'], 200);
    }

    public function destroy($mId, $faId)
    {
        $boundary = DBBoundary::where('M_ID', $mId)->where('Fa_ID', $faId)->firstOrFail();
        $boundary->delete();

        return redirect()->route('db-boundary.list', ['mId' => $mId])
            ->with('success', '邊界設定刪除成功');
    }
    public function toggle(Request $request, $mId, $faId)
    {
        $request->validate([
            'DBB_Status_stop' => 'required|in:0,1',
        ]);

        $boundary = DBBoundary::where('M_ID', $mId)->where('Fa_ID', $faId)->firstOrFail();
        $newStatus = $request->input('DBB_Status_stop');



        $boundary->DBB_Status_stop = $newStatus;
        $boundary->save();

        return response()->json([
            'success' => true,
            'message' => '狀態更新成功',
        ], 200);
    }
}