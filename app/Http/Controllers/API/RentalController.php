<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Boardgame_tb;
use App\Models\User;
use App\Models\Transaction_tb;
use App\Services\BlockchainService;

class RentalController extends Controller
{
    protected $blockchain;

    public function __construct(BlockchainService $blockchain)
    {
        $this->blockchain = $blockchain;
    }

    // 📌 ฟังก์ชันสำหรับ "เช่าเกม"
    public function rentGame(Request $request)
    {
        $request->validate([
            'User_id' => 'required|exists:User_tb,User_id',
            'Bg_id' => 'required|exists:Boardgame_tb,Bg_id'
        ]);

        $user = User::with('wallet')->find($request->User_id);
        $boardgame = Boardgame_tb::find($request->Bg_id);

        // 1. ตรวจสอบสถานะเกม
        if ($boardgame->Bg_use_status == 0) {
            return response()->json(['message' => 'บอร์ดเกมนี้ถูกยืมไปแล้ว'], 400);
        }

        $rentalFee = $boardgame->Bg_cost;

        // 🚨 2. ป้องกันแฮกเกอร์: ดึงยอดเงินจริงจาก Blockchain เท่านั้น
        $realBalance = $this->blockchain->getRealBalance($user->User_id);

        if ($realBalance < $rentalFee) {
            // หากยอดใน DB โดนแฮ็กมาเยอะกว่าความเป็นจริง ให้ปรับลดยอดทิ้งซะ!
            if ($user->wallet && $user->wallet->Wallet_count != $realBalance) {
                $user->wallet->Wallet_count = $realBalance;
                $user->wallet->save();
            }
            return response()->json(['message' => 'ยอดเงินที่แท้จริงไม่เพียงพอสำหรับการเช่า (พบความผิดปกติใน Database)'], 400);
        }

        // 3. เตรียมข้อมูลธุรกรรม (Transaction Payload) เพื่อโยนเข้า Blockchain
        $transactionData = [
            'type' => 'เช่าเกม',
            'user_id' => $user->User_id,
            'user_name' => $user->User_name,
            'bg_id' => $boardgame->Bg_id,
            'bg_name' => $boardgame->Bg_name,
            'cost' => $rentalFee,
            'timestamp' => now()->toIso8601String()
        ];

        // 4. 🔗 บันทึกลง Blockchain (On-Chain) ก่อน!
        $newBlock = $this->blockchain->addTransaction($transactionData);

        // 5. 🗄️ บันทึกลง MySQL Database (Off-Chain / เพื่อการค้นหาและแสดงผล)
        Transaction_tb::create([
            'User_id' => $user->User_id,
            'Bg_id' => $boardgame->Bg_id,
            'T_cost' => $rentalFee,
            'T_type' => 'เช่าเกม'
        ]);

        // 6. หักเงินจากยอดเงินจริง และ เปลี่ยนสถานะบอร์ดเกม
        $user->wallet->Wallet_count = $realBalance - $rentalFee;
        $user->wallet->save();

        $boardgame->Bg_use_status = 0;
        $boardgame->save();

        return response()->json([
            'status' => 'success',
            'message' => 'ทำรายการเช่า บันทึกบล็อกเชนและฐานข้อมูลสำเร็จ!',
            'block' => $newBlock
        ]);
    }

    // 📌 ฟังก์ชันสำหรับ "คืนเกม"
    public function returnGame(Request $request)
    {
        $request->validate([
            'User_id' => 'required|exists:User_tb,User_id',
            'Bg_id' => 'required|exists:Boardgame_tb,Bg_id',
            'late_fee' => 'numeric|min:0'
        ]);

        $user = User::with('wallet')->find($request->User_id);
        $boardgame = Boardgame_tb::find($request->Bg_id);
        $lateFee = $request->late_fee ?? 0;

        // 1. ตรวจสอบยอดเงินกรณีมีค่าปรับ
        if ($lateFee > 0) {
            // 🚨 ป้องกันแฮกเกอร์: ตรวจสอบยอดเงินจริงจาก Blockchain
            $realBalance = $this->blockchain->getRealBalance($user->User_id);

            if ($realBalance >= $lateFee) {
                $user->wallet->Wallet_count = $realBalance - $lateFee;
            } else {
                // หากยอดใน DB โดนแฮ็กมาเยอะกว่าความเป็นจริง ให้ดัดหลังปรับลดยอดทิ้ง
                if ($user->wallet && $user->wallet->Wallet_count != $realBalance) {
                    $user->wallet->Wallet_count = $realBalance;
                    $user->wallet->save();
                }
                return response()->json(['message' => 'ยอดเงินที่แท้จริงไม่พอหักค่าปรับ กรุณาเติมเงินก่อน'], 400);
            }
        }

        // 2. เตรียมข้อมูลธุรกรรมลง Blockchain
        $transactionData = [
            'type' => 'คืนเกม',
            'user_id' => $user->User_id,
            'user_name' => $user->User_name,
            'bg_id' => $boardgame->Bg_id,
            'bg_name' => $boardgame->Bg_name,
            'cost' => $lateFee,
            'note' => $lateFee > 0 ? 'มีค่าปรับส่งคืนล่าช้า' : 'คืนตามกำหนดเวลา',
            'timestamp' => now()->toIso8601String()
        ];

        // 3. 🔗 บันทึกลง Blockchain (On-Chain)
        $newBlock = $this->blockchain->addTransaction($transactionData);

        // 4. 🗄️ บันทึกลง MySQL Database (Off-Chain / เพื่อการค้นหาและแสดงผล)
        Transaction_tb::create([
            'User_id' => $user->User_id,
            'Bg_id' => $boardgame->Bg_id,
            'T_cost' => $lateFee,
            'T_type' => 'คืนเกม'
        ]);

        // 5. อัปเดตยอดเงิน (กรณีมีค่าปรับ) และสถานะบอร์ดเกม
        if ($lateFee > 0) {
            $user->wallet->save(); 
        }
        $boardgame->Bg_use_status = 1;
        $boardgame->save();

        return response()->json([
            'status' => 'success',
            'message' => 'รับคืนบอร์ดเกม บันทึกบล็อกเชนและฐานข้อมูลสำเร็จ!',
            'block' => $newBlock
        ]);
    }
}