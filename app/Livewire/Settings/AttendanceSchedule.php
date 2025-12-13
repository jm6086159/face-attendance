<?php

namespace App\Livewire\Settings;

use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Models\Setting;

#[Layout('components.layouts.app')]
class AttendanceSchedule extends Component
{
    public string $in_start = '06:00';
    public string $in_end   = '08:00';
    public string $out_start= '16:00';
    public string $out_end  = '17:00';
    public array $days = [1,2,3,4,5]; // 1=Mon .. 7=Sun
    public ?string $from_date = null; // YYYY-MM-DD
    public ?string $to_date   = null; // YYYY-MM-DD
    public ?string $late_after = null; // HH:MM optional
    public int $late_grace = 0; // minutes

    public function mount(): void
    {
        $cfg = Setting::getValue('attendance.schedule');
        if ($cfg) {
            $this->in_start  = $cfg['in_start'] ?? $this->in_start;
            $this->in_end    = $cfg['in_end'] ?? $this->in_end;
            $this->out_start = $cfg['out_start'] ?? $this->out_start;
            $this->out_end   = $cfg['out_end'] ?? $this->out_end;
            $this->days      = $cfg['days'] ?? $this->days;
            $this->from_date = $cfg['from_date'] ?? null;
            $this->to_date   = $cfg['to_date'] ?? null;
            $this->late_after = $cfg['late_after'] ?? null;
            $this->late_grace = (int)($cfg['late_grace'] ?? 0);
        }
    }

    public function save(): void
    {
        $this->validate([
            'in_start'  => 'required|date_format:H:i',
            'in_end'    => 'required|date_format:H:i',
            'out_start' => 'required|date_format:H:i',
            'out_end'   => 'required|date_format:H:i',
            'days'      => 'array|min:1',
            'days.*'    => 'integer|min:1|max:7',
            'from_date' => 'nullable|date',
            'to_date'   => 'nullable|date|after_or_equal:from_date',
            'late_after'=> 'nullable|date_format:H:i',
            'late_grace'=> 'integer|min:0|max:180',
        ]);

        Setting::setValue('attendance.schedule', [
            'in_start'  => $this->in_start,
            'in_end'    => $this->in_end,
            'out_start' => $this->out_start,
            'out_end'   => $this->out_end,
            'days'      => array_values($this->days),
            'from_date' => $this->from_date,
            'to_date'   => $this->to_date,
            'late_after'=> $this->late_after,
            'late_grace'=> $this->late_grace,
        ]);

        session()->flash('status', 'Attendance schedule saved.');
    }

    public function render()
    {
        return view('livewire.settings.attendance-schedule');
    }
}
