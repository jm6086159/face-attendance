<?php

namespace App\Livewire\Dashboard;

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AttendanceChart extends Component
{
    public $chartData = [];

    public function mount()
    {
        $start = Carbon::now()->startOfWeek();
        $end   = Carbon::now()->endOfWeek();

        // Aggregate distinct employees who checked in per day
        $rows = DB::table('attendance_logs')
            ->selectRaw('DATE(COALESCE(logged_at, created_at)) as d, COUNT(DISTINCT employee_id) as c')
            ->whereIn('action', ['time_in','check_in','in'])
            ->whereBetween(DB::raw('COALESCE(logged_at, created_at)'), [$start, $end])
            ->groupBy('d')
            ->orderBy('d')
            ->get()
            ->keyBy('d');

        $labels = [];
        $values = [];
        $cursor = $start->copy();
        while ($cursor->lessThanOrEqualTo($end)) {
            $d = $cursor->toDateString();
            $labels[] = $cursor->format('D');
            $values[] = (int) ($rows[$d]->c ?? 0);
            $cursor->addDay();
        }

        $this->chartData = [
            'labels' => $labels,
            'values' => $values,
        ];
    }

    public function render()
    {
        return view('livewire.dashboard.attendance-chart');
    }
}
