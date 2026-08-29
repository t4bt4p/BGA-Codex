<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Transaction_tb;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        // 📌 ดึงประวัติทั้งหมด พร้อมข้อมูล user และ boardgame เรียงจากใหม่ไปเก่า
        $query = Transaction_tb::with(['user', 'boardgame'])
            ->orderBy('created_at', 'desc')
            ;
        if ($request->user()->User_role !== 'admin') {
            $query->where('User_id', $request->user()->User_id);
        }
        $transactions = $query->get();
            
        return response()->json($transactions);
    }
}
