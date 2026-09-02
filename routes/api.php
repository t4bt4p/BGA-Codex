<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\BoardgameCategoryController;
use App\Http\Controllers\API\BoardgameController;
use App\Http\Controllers\API\OpnWebhookController;
use App\Http\Controllers\API\RentalController;
use App\Http\Controllers\API\ReportController;
use App\Http\Controllers\API\TransactionController;
use App\Http\Controllers\API\UserController;
use App\Services\BlockchainService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route; //

// 🎯 เพิ่มเส้นทางสำหรับแอดมิน
Route::post('/admin/login', [AuthController::class, 'adminLogin']);

// เส้นทางสำหรับ Login
Route::post('/login', [AuthController::class, 'login']);
// เส้นทางสำหรับ สมัครสมาชิก
Route::post('/register', [AuthController::class, 'register']);
Route::post('/webhooks/opn', [OpnWebhookController::class, 'handle']);

// รายการสำหรับผู้เยี่ยมชม: ดูเกมได้ก่อนสมัคร แต่ทำธุรกรรมต้องยืนยันตัวตน
Route::get('/categories', [BoardgameCategoryController::class, 'index']);
Route::get('/boardgames', [BoardgameController::class, 'index']);

// เส้นทางที่ต้องใช้ Token (บัตรผ่าน) ถึงจะเข้าได้
Route::middleware('auth:sanctum')->group(function () {

    // เส้นทางสำหรับ Logout
    Route::post('/logout', [AuthController::class, 'logout']);

    // ดึงข้อมูล User ปัจจุบัน (Laravel ใส่มาให้เป็นค่าเริ่มต้น เก็บไว้ได้ครับ)
    Route::get('/user', function (Request $request) {
        return $request->user()->load('wallet'); // โหลดความสัมพันธ์ wallet ด้วย
    });
    Route::put('/user/profile', [UserController::class, 'updateProfile']);

    Route::get('/rentals/active', [RentalController::class, 'myActiveRentals']);
    Route::post('/rentals/rent', [RentalController::class, 'rentGame']);
    Route::post('/rentals/return', [RentalController::class, 'returnGame']);
    Route::get('/transactions', [TransactionController::class, 'index']);
    Route::post('/topups', [RentalController::class, 'createTopupRequest']);
    Route::post('/topups/{topup}/sync', [RentalController::class, 'syncTopup']);

    Route::middleware('admin')->group(function () {
        Route::post('/categories', [BoardgameCategoryController::class, 'store']);
        Route::post('/boardgames', [BoardgameController::class, 'store']);
        Route::delete('/boardgames/{id}', [BoardgameController::class, 'destroy']);
        Route::put('/boardgames/{id}', [BoardgameController::class, 'update']);
        Route::get('/users', [UserController::class, 'index']);
        Route::get('/rentals', [RentalController::class, 'allRentals']);
        Route::put('/users/{id}/status', [UserController::class, 'updateStatus']);
        Route::put('/users/{id}/wallet', [UserController::class, 'updateWallet']);
        Route::get('/topups', [RentalController::class, 'topupRequests']);
        Route::get('/blockchain/verify', function (BlockchainService $blockchain) {
            return response()->json($blockchain->verifyChain());
        });
        Route::post('/blockchain/repair', function (BlockchainService $blockchain) {
            return response()->json($blockchain->repairNetwork());
        });
        Route::get('/reports/dashboard', [ReportController::class, 'dashboard']);
        Route::post('/transactions/{transaction}/polygon/retry', [TransactionController::class, 'retryPolygon']);
    });

});
