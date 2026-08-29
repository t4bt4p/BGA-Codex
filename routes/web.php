<?php

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\MobileOnly;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// 🎯 ครอบ Route หลักด้วย Middleware ให้ใช้งานได้เฉพาะบนมือถือเท่านั้น
Route::middleware([MobileOnly::class])->group(function () {
    
    // เส้นทางสำหรับโหลดหน้าแอปพลิเคชัน Vue.js ทั้งหมด (SPA)
    Route::get('/{any}', function () {
        return view('welcome'); // 💡 (หมายเหตุ: ถ้าในโปรเจกต์ของคุณใช้ชื่อวิวอื่น เช่น 'app' หรือ 'layouts.app' สามารถเปลี่ยนชื่อตรงนี้ให้ตรงกับโปรเจกต์ของคุณได้เลยครับ)
    })->where('any', '.*');

});