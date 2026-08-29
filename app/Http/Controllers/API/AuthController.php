<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Wallet_tb; // จำเป็นต้อง import โมเดลนี้เพื่อใช้ตอน Register

class AuthController extends Controller
{
    // -----------------------------------------------------
    // ฝั่งลูกค้าทั่วไป (User)
    // -----------------------------------------------------
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required',
            'password' => 'required',
        ]);

        $credentials = [
            'User_username' => $request->username,
            'password'      => $request->password,
            'User_status'   => 1,
            'User_role'     => 'user',
        ];

        if (Auth::attempt($credentials)) {
            
            /** @var \App\Models\User $user */
            $user = Auth::user();
            
            $token = $user->createToken('user-token')->plainTextToken;
            
            return response()->json([
                'status' => 'success',
                'message' => 'เข้าสู่ระบบสำเร็จ',
                'token' => $token,
                'user' => $user
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง'
        ], 401);
    }

    public function register(Request $request)
    {
        $request->validate([
            'User_name' => 'required|string|max:255',
            'username'  => 'required|string|unique:User_tb,User_username|max:255',
            'password'  => 'required|string|min:6',
            'User_phone'=> 'nullable|string|max:15',
        ]);

        [$user, $wallet] = DB::transaction(function () use ($request) {
            $wallet = Wallet_tb::create(['Wallet_count' => 0]);
            $user = User::create([
                'User_name'     => $request->User_name,
                'User_username' => $request->username,
                'User_password' => bcrypt($request->password),
                'User_phone'    => $request->User_phone,
                'User_status'   => 1,
                'User_role'     => 'user',
                'Wallet_id'     => $wallet->Wallet_id,
            ]);
            return [$user, $wallet];
        });

        $token = $user->createToken('user-token')->plainTextToken;

        return response()->json([
            'status'  => 'success',
            'message' => 'สมัครสมาชิกและเปิดกระเป๋าเงินสำเร็จ!',
            'token'   => $token,
            'user'    => $user
        ], 201);
    }

    // -----------------------------------------------------
    // ฝั่งแอดมิน (Admin)
    // -----------------------------------------------------
    public function adminLogin(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $credentials = [
            'User_username' => $request->username,
            'password'      => $request->password,
            'User_status'   => 1,
            'User_role'     => 'admin',
        ];

        if (Auth::attempt($credentials)) {
            
            /** @var \App\Models\User $user */
            $user = Auth::user();
            
            $token = $user->createToken('admin-token')->plainTextToken;
            
            return response()->json([
                'status' => 'success',
                'token' => $token,
                'user' => $user
            ]);
        }

        // 🎯 ส่งกลับเป็นสถานะ 422 ป้องกันระบบ Global เตะกลับไปหน้า Login ธรรมดา
        return response()->json([
            'status' => 'error',
            'message' => 'ชื่อผู้ใช้/รหัสผ่านไม่ถูกต้อง หรือคุณไม่มีสิทธิ์เข้าถึงระบบ'
        ], 422);
    }

    // -----------------------------------------------------
    // ล็อกเอาท์ (ใช้ร่วมกันได้)
    // -----------------------------------------------------
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'ออกจากระบบสำเร็จเรียบร้อย'
        ]);
    }
}
