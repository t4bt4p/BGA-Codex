<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

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

    // ปิดการปรับยอดเงินโดยผู้ดูแลระบบ รวมถึงคำขอจากหน้าเว็บเวอร์ชันเก่า
    public function updateWallet(Request $request, $id)
    {
        abort(403, 'ผู้ดูแลระบบไม่สามารถเติมเงินหรือหักเงินของผู้ใช้งานได้');
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
