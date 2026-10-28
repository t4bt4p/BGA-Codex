<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'User_tb';

    protected $primaryKey = 'User_id';

    protected $fillable = [
        'User_username',
        'User_password',
        'User_phone',
        'User_name',
        'Wallet_id',
        'User_status',
        'User_role',
    ];

    protected $hidden = [
        'User_password',
    ];

    public function getAuthIdentifierName()
    {
        return 'User_id';
    }

    public function getAuthPassword()
    {
        return $this->User_password;
    }

    public function getAuthPasswordName()
    {
        return 'User_password';
    }

    // 📌 เพิ่มฟังก์ชันเชื่อมความสัมพันธ์ไปหาตาราง Wallet
    public function wallet()
    {
        // belongsTo(ชื่อโมเดลปลายทาง, 'ชื่อคอลัมน์ FK ในตารางนี้', 'ชื่อคอลัมน์ PK ในตารางปลายทาง')
        return $this->belongsTo(Wallet_tb::class, 'Wallet_id', 'Wallet_id');
    }

    public function rentals(): HasMany
    {
        return $this->hasMany(Rental_tb::class, 'User_id', 'User_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction_tb::class, 'User_id', 'User_id');
    }

    public function topupRequests(): HasMany
    {
        return $this->hasMany(Topup_request_tb::class, 'User_id', 'User_id');
    }
}
