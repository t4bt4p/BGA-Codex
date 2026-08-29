<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction_tb extends Model
{
    use HasFactory;

    protected $table = 'Transaction_tb';
    protected $primaryKey = 'Ts_id';

    // 📌 อนุญาตให้บันทึกข้อมูลลง 4 คอลัมน์นี้ได้
    protected $fillable = [
        'User_id',
        'Bg_id',
        'T_cost',
        'T_type',
    ];

    // 📌 ผูกความสัมพันธ์กลับไปหา User
    public function user()
    {
        return $this->belongsTo(User::class, 'User_id', 'User_id');
    }
    // 📌 ผูกความสัมพันธ์ไปหาตาราง Boardgame (เผื่อไว้ใช้ตอนแสดงประวัติการเช่า)
    public function boardgame()
    {
        return $this->belongsTo(Boardgame_tb::class, 'Bg_id', 'Bg_id');
    }
}