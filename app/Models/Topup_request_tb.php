<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Topup_request_tb extends Model {
    protected $table='Topup_request_tb'; protected $primaryKey='Topup_id';
    protected $fillable=['User_id','Amount','Reference','Provider_charge_id','Status','approved_at','approved_by'];
    public function user(){ return $this->belongsTo(User::class,'User_id','User_id'); }
}
