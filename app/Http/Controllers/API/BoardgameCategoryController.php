<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Boardgame_category_tb; // นำเข้า Model หมวดหมู่

class BoardgameCategoryController extends Controller
{
    // 1. ดึงรายชื่อหมวดหมู่ทั้งหมด
    public function index()
    {
        $categories = Boardgame_category_tb::all();
        return response()->json($categories);
    }

    // 2. เพิ่มหมวดหมู่ใหม่ (รองรับการกดเพิ่มจากหน้าฟอร์มบอร์ดเกม)
    public function store(Request $request)
    {
        $request->validate([
            'Bg_category_name' => 'required|string|max:255|unique:Boardgame_category_tb,Bg_category_name',
        ]);

        $category = Boardgame_category_tb::create([
            'Bg_category_name' => $request->Bg_category_name
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'เพิ่มหมวดหมู่สำเร็จ',
            'data' => $category
        ], 201);
    }
}
