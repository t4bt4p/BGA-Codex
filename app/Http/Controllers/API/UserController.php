<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Transaction_tb; 
use Illuminate\Http\Request;
use App\Services\BlockchainService; // 📌 1. นำเข้า Blockchain Service

class UserController extends Controller
{
    protected $blockchain;

    // 📌 2. ฉีด BlockchainService ผ่าน Constructor
    public function __construct(BlockchainService $blockchain)
    {
        $this->blockchain = $blockchain;
    }

    // ดึงข้อมูลผู้ใช้ทั้งหมด
    public function index()
    {
        // 📌 สั่งดึงข้อมูล User พร้อมข้อมูล Wallet ที่เชื่อมกันอยู่
        $users = User::with('wallet')->get();
        return response()->json($users);
    }

    // ฟังก์ชันสำหรับระงับ/เปิดใช้งานบัญชี (เปลี่ยน User_status)
    public function updateStatus(Request $request, $id)
    {
        $user = User::find($id);
        
        if (!$user) {
            return response()->json(['message' => 'ไม่พบผู้ใช้งาน'], 404);
        }

        $request->validate([
            'User_status' => 'required|integer|in:0,1' // สมมติ 1 = ใช้งานได้, 0 = ระงับ
        ]);

        $user->update(['User_status' => $request->User_status]);

        return response()->json([
            'status' => 'success',
            'message' => 'อัปเดตสถานะสำเร็จ'
        ]);
    }
    
    // 📌 ฟังก์ชันสำหรับจัดการกระเป๋าเงิน (เติมเงิน / หักเงิน)
    public function updateWallet(Request $request, $id)
    {
        // ค้นหา User พร้อมกับ Wallet
        $user = User::with('wallet')->find($id);

        if (!$user || !$user->wallet) {
            return response()->json(['message' => 'ไม่พบกระเป๋าเงินของผู้ใช้งานนี้'], 404);
        }

        $request->validate([
            'amount' => 'required|numeric|min:1',
            'action' => 'required|string|in:add,deduct'
        ]);

        $wallet = $user->wallet;
        $amount = $request->amount;
        
        // 🎯 [แก้ไขแล้ว] เปลี่ยนให้คำตรงกับหน้า Vue (History.vue) 
        $transactionType = $request->action === 'add' ? 'topup_credit' : 'admin_debit';

        if ($request->action === 'add') {
            // เติมเงิน
            $wallet->Wallet_count += $amount;
        } else {
            // หักเงิน (เช็คก่อนว่าเงินพอให้หักไหม)
            if ($wallet->Wallet_count < $amount) {
                return response()->json(['message' => 'ยอดเงินในกระเป๋าไม่เพียงพอให้หัก'], 400);
            }
            $wallet->Wallet_count -= $amount;
        }

        // 📌 3. เตรียมข้อมูลและบันทึกลง Blockchain (On-Chain)
        $transactionData = [
            'type' => $transactionType,
            'user_id' => $user->User_id,
            'user_name' => $user->User_name,
            'bg_id' => null,   // เติม/หักเงิน ไม่เกี่ยวกับบอร์ดเกม
            'bg_name' => '-',
            'cost' => $amount,
            'timestamp' => now()->toIso8601String()
        ];
        
        $newBlock = $this->blockchain->addTransaction($transactionData);

        // 📌 4. ระบบบันทึกประวัติธุรกรรมลง DB (Off-Chain)
        Transaction_tb::create([
            'User_id' => $user->User_id,
            'Bg_id' => null, // เป็น null เพราะไม่ได้เกี่ยวกับการเช่าเกม
            'T_cost' => $amount,
            'T_type' => $transactionType, // 👈 ตรงนี้จะถูกบันทึกเป็น 'เติมเงิน' แล้ว
        ]);

        // อัปเดตยอดเงินในกระเป๋า
        $wallet->save();

        return response()->json([
            'status' => 'success',
            'message' => 'อัปเดตยอดเงินสำเร็จ และบันทึกลงบล็อกเชนเรียบร้อย!',
            'balance' => $wallet->Wallet_count,
            'block' => $newBlock // ส่งข้อมูลบล็อกใหม่กลับไปให้หน้าเว็บเผื่อต้องการใช้งาน
        ]);
    }
}
