<?php
namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;
use App\Models\{Rental_tb,Transaction_tb};
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller {
 public function dashboard() {
  $today=Carbon::today(); $month=Carbon::now()->startOfMonth();
  $rentToday=Rental_tb::where('rented_at','>=',$today)->count();
  $monthRevenue=Rental_tb::where('rented_at','>=',$month)->sum('Rental_cost');
  $active=Rental_tb::where('Rental_status','active')->count();
  $overdue=Rental_tb::where('Rental_status','active')->where('due_at','<',now())->count();
  $popular=Rental_tb::select('Bg_id',DB::raw('COUNT(*) as rental_count'))->with('boardgame')->groupBy('Bg_id')->orderByDesc('rental_count')->limit(5)->get();
  $daily=[]; for($i=6;$i>=0;$i--){$date=Carbon::today()->subDays($i);$daily[]=['label'=>$date->format('d/m'),'count'=>Rental_tb::whereDate('rented_at',$date)->count()];}
  return response()->json(['rentals_today'=>$rentToday,'revenue_month'=>(int)$monthRevenue,'active_rentals'=>$active,'overdue_rentals'=>$overdue,'popular_games'=>$popular,'daily_rentals'=>$daily]);
 }
}
