<?php

use Illuminate\Support\Facades\Route;

// ให้ Laravel ส่งทุกๆ URL (รวมถึง /admin) ไปที่ไฟล์ welcome.blade.php เพื่อให้ Vue Router รับช่วงต่อ
Route::get('/{any}', function () {
    return view('welcome');
})->where('any', '.*');