<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Boardgame_Category_tb extends Model
{
    use HasFactory;

    // 1. ระบุชื่อตารางให้ชัดเจน
    protected $table = 'Boardgame_category_tb';

    // 2. ระบุชื่อ Primary Key (เพราะของเราไม่ได้ชื่อ id)
    protected $primaryKey = 'Bg_category_id';

    // 3. อนุญาตให้บันทึกคอลัมน์นี้ผ่านคำสั่ง create() ได้
    protected $fillable = [
        'Bg_category_name'
    ];
}