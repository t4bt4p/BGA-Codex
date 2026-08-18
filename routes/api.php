<?php

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\BoardgameCategoryController;
use App\Http\Controllers\API\BoardgameController;
use App\Http\Controllers\API\TransactionController;
use App\Http\Controllers\API\RentalController; 
use App\Http\Controllers\API\UserController;

use App\Services\BlockchainService; // 

// เส้นทางสำหรับ Login (ใครก็เข้าถึงได้)
Route::post('/login', [AuthController::class, 'login']);

// เส้นทางที่ต้องใช้ Token (บัตรผ่าน) ถึงจะเข้าได้
Route::middleware('auth:sanctum')->group(function () {
    
    // เส้นทางสำหรับ Logout
    Route::post('/logout', [AuthController::class, 'logout']);
    
    // ดึงข้อมูล User ปัจจุบัน (Laravel ใส่มาให้เป็นค่าเริ่มต้น เก็บไว้ได้ครับ)
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

});

// เส้นทางสำหรับจัดการหมวดหมู่บอร์ดเกม (ต้องล็อกอินก่อนถึงจะจัดการได้)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/categories', [BoardgameCategoryController::class, 'index']);
    Route::post('/categories', [BoardgameCategoryController::class, 'store']);

    // เส้นทางสำหรับจัดการบอร์ดเกม (ต้องล็อกอินก่อน)
    Route::get('/boardgames', [BoardgameController::class, 'index']);
    Route::post('/boardgames', [BoardgameController::class, 'store']);

    // เส้นทางสำหรับลบบอร์ดเกม (ต้องล็อกอินก่อน)
    Route::delete('/boardgames/{id}', [BoardgameController::class, 'destroy']);
    // เส้นทางสำหรับแก้ไขบอร์ดเกม (ต้องล็อกอินก่อน)
    Route::put('/boardgames/{id}', [BoardgameController::class, 'update']);

    // เส้นทางสำหรับจัดการผู้ใช้ (ต้องล็อกอินก่อน)
    Route::get('/users', [UserController::class, 'index']);
    Route::put('/users/{id}/status', [UserController::class, 'updateStatus']);

    // 📌 เพิ่มเส้นทางนี้สำหรับจัดการ Wallet
    Route::put('/users/{id}/wallet', [UserController::class, 'updateWallet']);


    // เพิ่มต่อจาก route ของ user
    Route::get('/transactions', [TransactionController::class, 'index']);
    

    // เพิ่ม 2 เส้นทางนี้สำหรับจัดการเช่าและคืน
    Route::post('/rentals/rent', [RentalController::class, 'rentGame']);
    Route::post('/rentals/return', [RentalController::class, 'returnGame']);

});

Route::get('/blockchain/verify', function(BlockchainService $blockchain) {
    return response()->json($blockchain->verifyChain());
});

Route::post('/wallet/topup', [RentalController::class, 'topupWallet']);

