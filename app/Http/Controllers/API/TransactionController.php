<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Transaction_tb;
use App\Jobs\AnchorTransactionOnPolygon;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        // 📌 ดึงประวัติทั้งหมด พร้อมข้อมูล user และ boardgame เรียงจากใหม่ไปเก่า
        $query = Transaction_tb::with(['user', 'boardgame', 'rental'])
            ->orderBy('created_at', 'desc')
            ;
        if ($request->user()->User_role !== 'admin') {
            $query->where('User_id', $request->user()->User_id);
        }
        $transactions = $query->get();
            
        return response()->json($transactions);
    }

    public function retryPolygon(Transaction_tb $transaction)
    {
        abort_unless(config('services.polygon.enabled'), 503, 'ยังไม่ได้เปิดใช้งาน Polygon Amoy');
        $transaction->update(['Chain_status' => 'pending', 'Chain_error' => null]);
        AnchorTransactionOnPolygon::dispatch((int) $transaction->Ts_id)->afterCommit();

        return response()->json(['status' => 'queued']);
    }
}
