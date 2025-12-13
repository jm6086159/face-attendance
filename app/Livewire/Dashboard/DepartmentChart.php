<?php

namespace App\Livewire\Dashboard;

use Livewire\Component;
use Illuminate\Support\Facades\DB;

class DepartmentChart extends Component
{
    public array $labels = [];
    public array $values = [];

    public function mount(): void
    {
        // If you have a 'department' column on employees, this will work out of the box.
        // Otherwise, replace 'department' with the correct column or provide your own data here.
        try {
            $rows = DB::table('employees')
                ->select('department', DB::raw('COUNT(*) as total'))
                ->groupBy('department')
                ->orderBy('department')
                ->get();

            if ($rows->count()) {
                $this->labels = $rows->pluck('department')->map(fn($v) => $v ?? 'Unassigned')->all();
                $this->values = $rows->pluck('total')->all();
            } else {
                // fallback when table is empty
                $this->labels = ['Unassigned'];
                $this->values = [0];
            }
        } catch (\Throwable $e) {
            // fallback when column doesn't exist or any query issue
            $this->labels = ['Sample A', 'Sample B'];
            $this->values = [5, 3];
        }
    }

    public function render()
    {
        return view('livewire.dashboard.department-chart');
    }
}
