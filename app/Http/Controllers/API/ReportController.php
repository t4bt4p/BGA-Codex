<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Rental_tb;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function dashboard(Request $request)
    {
        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        [$from, $to, $label] = $this->period($validated);
        abort_if($from->diffInDays($to) > 92, 422, 'ช่วงรายงานต้องไม่เกิน 93 วัน');

        $periodRentals = Rental_tb::whereBetween('rented_at', [$from, $to]);
        $popular = Rental_tb::select('Bg_id', DB::raw('COUNT(*) as rental_count'))
            ->whereBetween('rented_at', [$from, $to])
            ->with('boardgame')
            ->groupBy('Bg_id')
            ->orderByDesc('rental_count')
            ->limit(5)
            ->get();

        $countsByDate = Rental_tb::whereBetween('rented_at', [$from, $to])
            ->selectRaw('DATE(rented_at) as rental_date, COUNT(*) as rental_count')
            ->groupBy('rental_date')
            ->pluck('rental_count', 'rental_date');
        $daily = collect(CarbonPeriod::create($from->copy()->startOfDay(), $to->copy()->startOfDay()))
            ->map(fn (Carbon $date) => [
                'date' => $date->toDateString(),
                'label' => $date->format('d/m'),
                'count' => (int) ($countsByDate[$date->toDateString()] ?? 0),
            ])->values();

        return response()->json([
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'label' => $label],
            'period_rentals' => (clone $periodRentals)->count(),
            'period_revenue' => (int) (clone $periodRentals)->sum('Rental_cost'),
            'rentals_today' => Rental_tb::whereDate('rented_at', Carbon::today())->count(),
            'active_rentals' => Rental_tb::where('Rental_status', 'active')->count(),
            'overdue_rentals' => Rental_tb::where('Rental_status', 'active')->where('due_at', '<', now())->count(),
            'popular_games' => $popular,
            'daily_rentals' => $daily,
        ]);
    }

    private function period(array $validated): array
    {
        if (! empty($validated['month'])) {
            $month = Carbon::createFromFormat('Y-m', $validated['month'])->startOfMonth();

            return [$month->copy()->startOfDay(), $month->copy()->endOfMonth()->endOfDay(), $month->translatedFormat('F Y')];
        }
        if (! empty($validated['from']) && ! empty($validated['to'])) {
            $from = Carbon::parse($validated['from'])->startOfDay();
            $to = Carbon::parse($validated['to'])->endOfDay();

            return [$from, $to, $from->format('d/m/Y').' – '.$to->format('d/m/Y')];
        }
        $month = Carbon::now()->startOfMonth();

        return [$month->copy()->startOfDay(), $month->copy()->endOfMonth()->endOfDay(), $month->translatedFormat('F Y')];
    }
}
