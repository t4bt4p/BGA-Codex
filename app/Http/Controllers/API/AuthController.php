<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        // 1. เปลี่ยนจากการเช็ค email มาเช็ค username แทน
        $request->validate([
            'username' => 'required',
            'password' => 'required',
        ]);

        // 2. จับคู่ข้อมูลที่ส่งมา กับฟิลด์ในตาราง User_tb
        $credentials = [
            'User_username' => $request->username,
            'password'      => $request->password // Auth::attempt บังคับใช้คำว่า password แต่จะไปเช็คกับ User_password ให้อัตโนมัติ
        ];

        // 3. ตรวจสอบการเข้าสู่ระบบ
        if (Auth::attempt($credentials)) {
            
            // เพิ่มบรรทัดนี้เพื่อบอก VS Code ว่า $user คือ Model User ของเรา
            /** @var \App\Models\User $user */
            $user = Auth::user();
            
            $token = $user->createToken('admin-token')->plainTextToken;
            
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
    public function logout(Request $request)
    {
        // สั่งลบ Token ปัจจุบันที่ผู้ใช้คนนี้กำลังใช้งานอยู่
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'ออกจากระบบสำเร็จเรียบร้อย'
        ]);
    }
}