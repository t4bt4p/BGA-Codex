<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory; // 📌 เติมบรรทัดนี้
use Illuminate\Database\Eloquent\Model;

class Wallet_tb extends Model
{
    use HasFactory;

    protected $table = 'Wallet_tb';
    protected $primaryKey = 'Wallet_id';

    // 📌 (เสริม) ระบุคอลัมน์ที่อนุญาตให้บันทึกข้อมูล (ปรับแก้ตามฟิลด์ที่คุณมีใน ER ได้เลยครับ)
    // protected $fillable = ['Wallet_balance', ...];
    protected $fillable = ['Wallet_count',];

    // 📌 เพิ่มฟังก์ชันเชื่อมความสัมพันธ์กลับไปหาตาราง User
    public function user()
    {
        return $this->hasOne(User::class, 'Wallet_id', 'Wallet_id');
    }
}