<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request; 
use App\Models\Boardgame_tb;// นำเข้า Model บอร์ดเกม

class BoardgameController extends Controller
{
    // 1. ดึงรายการบอร์ดเกมทั้งหมด (พร้อมดึงข้อมูลหมวดหมู่มาแสดงด้วย)
    public function index()
    {
        $boardgames = Boardgame_tb::with('category')->get();
        return response()->json($boardgames);
    }

    // 2. บันทึกข้อมูลบอร์ดเกมใหม่
    public function store(Request $request)
    {
        $request->validate([
            'Bg_name' => 'required|string|max:255',
            'Bg_cost' => 'required|integer|min:0',
            'Bg_min_player' => 'required|integer|min:1',
            'Bg_max_player' => 'required|integer|gte:Bg_min_player',
            'Bg_playduration' => 'required|integer|min:1',
            'Bg_Catetogory_id' => 'required|exists:Boardgame_category_tb,Bg_category_id',
            'Bg_Image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048', // 👈 ตรวจสอบว่าเป็นไฟล์รูปภาพ ไม่เกิน 2MB
        ]);

        $data = $request->except('Bg_Image'); // ดึงข้อมูลทั้งหมดมาก่อน ยกเว้นรูป
        $data['Bg_use_status'] = 1;
        $data['Bg_Image'] = '';

        // เช็คว่ามีการแนบไฟล์รูปมาไหม?
        if ($request->hasFile('Bg_Image')) {
            // เซฟไฟล์ลงโฟลเดอร์ storage/app/public/boardgames
            $path = $request->file('Bg_Image')->store('boardgames', 'public');
            // สร้าง URL เก็บลงฐานข้อมูล
            $data['Bg_Image'] = '/storage/' . $path;
        }

        $boardgame = Boardgame_tb::create($data);

        return response()->json([
            'status' => 'success',
            'message' => 'เพิ่มบอร์ดเกมสำเร็จ',
            'data' => $boardgame
        ], 201);
    }
    // 3. ลบข้อมูลบอร์ดเกม
    public function destroy($id)
    {
        $boardgame = Boardgame_tb::find($id); // ค้นหาบอร์ดเกมจาก ID

        if (!$boardgame) {
            return response()->json([
                'status' => 'error',
                'message' => 'ไม่พบบอร์ดเกมนี้ในระบบ'
            ], 404);
        }

        $boardgame->delete(); // สั่งลบข้อมูล

        return response()->json([
            'status' => 'success',
            'message' => 'ลบข้อมูลสำเร็จ'
        ]);
    }
    // 4. แก้ไขข้อมูลบอร์ดเกม
    public function update(Request $request, $id)
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($request, $id) {
        $boardgame = Boardgame_tb::lockForUpdate()->find($id);

        if (!$boardgame) {
            return response()->json(['message' => 'ไม่พบบอร์ดเกม'], 404);
        }

        abort_if((int) $boardgame->Bg_use_status === 0 || \App\Models\Rental_tb::where('Bg_id', $id)->where('Rental_status', 'active')->exists(), 409, 'ไม่สามารถแก้ไขเกมที่กำลังถูกเช่าอยู่ กรุณารับคืนก่อน');
        $request->validate([
            'Bg_name' => 'required|string|max:255',
            'Bg_cost' => 'required|integer|min:0',
            'Bg_min_player' => 'required|integer|min:1',
            'Bg_max_player' => 'required|integer|gte:Bg_min_player',
            'Bg_playduration' => 'required|integer|min:1',
            'Bg_Catetogory_id' => 'required|exists:Boardgame_category_tb,Bg_category_id',
            'Bg_Image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $data = $request->only(['Bg_name', 'Bg_cost', 'Bg_min_player', 'Bg_max_player', 'Bg_playduration', 'Bg_Catetogory_id']);

        // ถ้ามีการอัปโหลดรูปภาพ "ใหม่" เข้ามาตอนแก้ไข
        if ($request->hasFile('Bg_Image')) {
            $path = $request->file('Bg_Image')->store('boardgames', 'public');
            $data['Bg_Image'] = '/storage/' . $path; // อัปเดต Path ใหม่
        } 
        // ถ้าไม่ได้อัปรูปใหม่ ระบบจะใช้ชื่อรูปเก่าที่มีอยู่ใน DB อัตโนมัติ

        $boardgame->update($data);

        return response()->json([
            'status' => 'success',
            'message' => 'แก้ไขข้อมูลสำเร็จ',
            'data' => $boardgame
        ]);
        });
    }
}
