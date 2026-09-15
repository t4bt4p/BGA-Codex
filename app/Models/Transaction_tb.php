<?php

namespace App\Models;

use App\Jobs\RecordPrivateBlockchainTransaction;
use Illuminate\Database\Eloquent\Model;

class Transaction_tb extends Model
{
    protected $table = 'Transaction_tb';
    protected $primaryKey = 'Ts_id';

    // 📌 อนุญาตให้บันทึกข้อมูลลง 4 คอลัมน์นี้ได้
    protected $fillable = [
        'User_id',
        'Bg_id',
        'Rental_id',
        'T_cost',
        'T_type',
        'Chain_network',
        'Chain_payload_hash',
        'Chain_tx_hash',
        'Chain_block_number',
        'Chain_status',
        'Chain_error',
        'Chain_confirmed_at',
    ];

    protected $casts = [
        'Chain_confirmed_at' => 'datetime',
    ];

    protected $appends = ['chain_explorer_url'];

    protected static function booted(): void
    {
        static::creating(function (Transaction_tb $transaction) {
            if (config('services.polygon.enabled')) {
                $transaction->Chain_network = config('services.polygon.network');
                $transaction->Chain_status = 'pending';
            }
        });

        static::created(function (Transaction_tb $transaction) {
            RecordPrivateBlockchainTransaction::dispatch((int) $transaction->Ts_id)->afterCommit();
        });
    }

    public function getChainExplorerUrlAttribute(): ?string
    {
        if (! $this->Chain_tx_hash) {
            return null;
        }

        return rtrim((string) config('services.polygon.explorer_url'), '/').'/tx/'.$this->Chain_tx_hash;
    }

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

    public function rental()
    {
        return $this->belongsTo(Rental_tb::class, 'Rental_id', 'Rental_id');
    }
}
