<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Rental_tb extends Model {
    protected $table = 'Rental_tb'; protected $primaryKey = 'Rental_id';
    protected $fillable = ['User_id','Bg_id','Rental_cost','rented_at','due_at','returned_at','late_fee','Rental_status'];
    protected $casts = ['rented_at'=>'datetime','due_at'=>'datetime','returned_at'=>'datetime'];
    public function user(){ return $this->belongsTo(User::class,'User_id','User_id'); }
    public function boardgame(){ return $this->belongsTo(Boardgame_tb::class,'Bg_id','Bg_id'); }
}
