<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Transaction_tb;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB; // 📌 1. นำเข้า Blockchain Service

class UserController extends Controller
{
    // ดึงข้อมูลผู้ใช้ทั้งหมด
    public function index()
    {
        app(\App\Services\OverdueAccountService::class)->suspendOverdueAccounts();
        // 📌 สั่งดึงข้อมูล User พร้อมข้อมูล Wallet ที่เชื่อมกันอยู่
        $users = User::with('wallet')->get();

        return response()->json($users);
    }

    // ฟังก์ชันสำหรับระงับ/เปิดใช้งานบัญชี (เปลี่ยน User_status)
    public function updateStatus(Request $request, $id)
    {
        $user = User::find($id);

        if (! $user) {
            return response()->json(['message' => 'ไม่พบผู้ใช้งาน'], 404);
        }

        $request->validate([
            'User_status' => 'required|integer|in:0,1', // สมมติ 1 = ใช้งานได้, 0 = ระงับ
        ]);

        abort_if((int) $request->User_status === 1
            && app(\App\Services\OverdueAccountService::class)->hasOverdue($user),
            422, 'ต้องคืนบอร์ดเกมที่เกินกำหนดก่อนเปิดใช้งานบัญชี');
        $user->update(['User_status' => $request->User_status]);

        return response()->json([
            'status' => 'success',
            'message' => 'อัปเดตสถานะสำเร็จ',
        ]);
    }

    // 📌 ฟังก์ชันสำหรับจัดการกระเป๋าเงิน (เติมเงิน / หักเงิน)
    public function updateWallet(Request $request, $id)
    {
        $request->validate([
            'amount' => 'required|integer|min:1',
            'action' => 'required|string|in:add,deduct',
        ]);
        $wallet = DB::transaction(function () use ($request, $id) {
            $user = User::findOrFail($id);
            $wallet = $user->wallet()->lockForUpdate()->firstOrFail();
            $amount = (int) $request->amount;
            $type = $request->action === 'add' ? 'topup_credit' : 'admin_debit';
            if ($type === 'admin_debit' && (int) $wallet->Wallet_count < $amount) {
                abort(422, 'ยอดเงินในกระเป๋าไม่เพียงพอให้หัก');
            }
            $newBalance = (int) $wallet->Wallet_count + ($type === 'topup_credit' ? $amount : -$amount);
            Transaction_tb::create(['User_id' => $user->User_id, 'Bg_id' => null, 'T_cost' => $amount, 'T_type' => $type]);
            $wallet->update(['Wallet_count' => $newBalance]);

            return $wallet->fresh();
        });

        return response()->json([
            'status' => 'success',
            'message' => 'อัปเดตยอดเงินสำเร็จ และส่งรายการไปบันทึกลงบล็อกเชนแล้ว',
            'balance' => $wallet->Wallet_count,
        ]);
    }

    public function updateProfile(Request $request)
    {
        $data = $request->validate([
            'User_name' => 'required|string|max:255',
            'User_phone' => ['nullable', 'string', 'max:15', 'regex:/^[0-9+ -]*$/'],
        ]);
        $request->user()->update($data);

        return response()->json(['status' => 'success', 'user' => $request->user()->fresh()->load('wallet')]);
    }
}
