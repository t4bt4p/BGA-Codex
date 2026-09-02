<?php
namespace App\Models;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
class Rental_tb extends Model {
    protected $table = 'Rental_tb'; protected $primaryKey = 'Rental_id';
    protected $fillable = ['User_id','Bg_id','Rental_cost','rental_days','daily_rate','rented_at','due_at','returned_at','late_fee','Rental_status'];
    protected $casts = ['rented_at'=>'datetime','due_at'=>'datetime','returned_at'=>'datetime'];
    protected $appends = ['is_overdue', 'overdue_days', 'accrued_late_fee'];
    public function user(){ return $this->belongsTo(User::class,'User_id','User_id'); }
    public function boardgame(){ return $this->belongsTo(Boardgame_tb::class,'Bg_id','Bg_id'); }

    public function calculateOverdueDays(?CarbonInterface $at = null): int
    {
        $at ??= now();
        $endedAt = $this->Rental_status === 'active' ? $at : ($this->returned_at ?? $at);
        if (! $this->due_at || $endedAt->lessThanOrEqualTo($this->due_at)) {
            return 0;
        }

        return (int) ceil(($endedAt->getTimestamp() - $this->due_at->getTimestamp()) / 86400);
    }

    public function calculateLateFee(?CarbonInterface $at = null): int
    {
        return $this->calculateOverdueDays($at) * (int) ($this->daily_rate ?: $this->Rental_cost);
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->Rental_status === 'active' && $this->calculateOverdueDays() > 0;
    }

    public function getOverdueDaysAttribute(): int
    {
        return $this->calculateOverdueDays();
    }

    public function getAccruedLateFeeAttribute(): int
    {
        return $this->Rental_status === 'active' ? $this->calculateLateFee() : (int) $this->late_fee;
    }
}
