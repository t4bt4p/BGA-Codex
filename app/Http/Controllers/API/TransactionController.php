<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Transaction_tb;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function index()
    {
        // 📌 ดึงประวัติทั้งหมด พร้อมข้อมูล user และ boardgame เรียงจากใหม่ไปเก่า
        $transactions = Transaction_tb::with(['user', 'boardgame'])
            ->orderBy('created_at', 'desc')
            ->get();
            
        return response()->json($transactions);
    }
}