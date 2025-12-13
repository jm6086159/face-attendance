<?php

namespace App\Livewire\Dashboard;

use Livewire\Component;
use App\Models\AttendanceLog;

class RecentLogs extends Component
{
    public function render()
    {
        $logs = AttendanceLog::with('employee')
            ->orderByRaw('COALESCE(`logged_at`, `created_at`) DESC')
            ->take(5)
            ->get();
        return view('livewire.dashboard.recent-logs', compact('logs'));
    }
}
