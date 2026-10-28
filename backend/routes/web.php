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

// Keep the mobile restriction available; USER_MOBILE_ONLY controls whether it is active.
Route::middleware([MobileOnly::class])->group(function () {
    
    // เส้นทางสำหรับโหลดหน้าแอปพลิเคชัน Vue.js ทั้งหมด (SPA)
    Route::get('/{any}', function (\Illuminate\Http\Request $request) {
        if ($request->is('admin') || $request->is('admin/*')) {
            $index = public_path('admin-ui/index.html');
            abort_unless(is_file($index), 503, 'Build backend/admin first: npm ci && npm run build');
            return response()->file($index);
        }
        return view('welcome'); // 💡 (หมายเหตุ: ถ้าในโปรเจกต์ของคุณใช้ชื่อวิวอื่น เช่น 'app' หรือ 'layouts.app' สามารถเปลี่ยนชื่อตรงนี้ให้ตรงกับโปรเจกต์ของคุณได้เลยครับ)
    })->where('any', '.*');

});
