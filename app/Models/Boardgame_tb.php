<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Boardgame_tb extends Model
{
    use SoftDeletes;

    // 1. ระบุชื่อตาราง
    protected $table = 'Boardgame_tb';

    // 2. ระบุชื่อ Primary Key
    protected $primaryKey = 'Bg_id';

    // 3. อนุญาตให้บันทึกคอลัมน์เหล่านี้ได้
    protected $fillable = [
        'Bg_name',
        'Bg_cost',
        'Bg_min_player',
        'Bg_max_player',
        'Bg_playduration',
        'Bg_Catetogory_id',
        'Bg_use_status',
        'Bg_Image'
    ];

// เปลี่ยนจาก BoardgameCategory::class เป็นชื่อโมเดลจริงๆ ของคุณ
    public function category()
    {
        return $this->belongsTo(Boardgame_category_tb::class, 'Bg_Catetogory_id', 'Bg_category_id');
    }
}
