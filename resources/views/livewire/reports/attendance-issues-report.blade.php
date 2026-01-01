<div class="p-6 space-y-6 text-gray-900">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold text-white">Attendance Issues Report</h1>
            <p class="text-sm text-white/80">Monitor Late, Undertime, Absent, and Overtime records. Identify patterns and generate reports.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <button wire:click="exportExcel" class="px-3 py-2 rounded-lg text-sm text-white" style="background:#0f8b48;">
                <span wire:loading.remove wire:target="exportExcel">Export Excel</span>
                <span wire:loading wire:target="exportExcel">Exporting...</span>
            </button>
            <button wire:click="exportPdf" class="px-3 py-2 rounded-lg text-sm border border-gray-200 text-gray-700 bg-white">
                <span wire:loading.remove wire:target="exportPdf">Export PDF</span>
                <span wire:loading wire:target="exportPdf">Generating...</span>
            </button>
        </div>
    </div>

    @if(session('status'))
        <div class="p-3 rounded-lg bg-amber-100 border border-amber-300 text-amber-800 text-sm">
            {{ session('status') }}
        </div>
    @endif

    {{-- Summary cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 shadow-sm cursor-pointer hover:shadow-md transition-shadow @if($issueType === 'late') ring-2 ring-amber-500 @endif" wire:click="$set('issueType', 'late')">
            <div class="text-xs text-amber-700 font-medium">Late</div>
            <div class="text-2xl font-bold text-amber-900">{{ number_format($summary['late_count']) }}</div>
            <div class="text-xs text-amber-600 mt-1">{{ $summary['total_late_hours'] ?? '00h 00m' }} total</div>
        </div>
        <div class="rounded-xl border border-orange-200 bg-orange-50 p-4 shadow-sm cursor-pointer hover:shadow-md transition-shadow @if($issueType === 'undertime') ring-2 ring-orange-500 @endif" wire:click="$set('issueType', 'undertime')">
            <div class="text-xs text-orange-700 font-medium">Undertime</div>
            <div class="text-2xl font-bold text-orange-900">{{ number_format($summary['undertime_count']) }}</div>
            <div class="text-xs text-orange-600 mt-1">{{ $summary['total_undertime_hours'] ?? '00h 00m' }} total</div>
        </div>
        <div class="rounded-xl border border-red-200 bg-red-50 p-4 shadow-sm cursor-pointer hover:shadow-md transition-shadow @if($issueType === 'absent') ring-2 ring-red-500 @endif" wire:click="$set('issueType', 'absent')">
            <div class="text-xs text-red-700 font-medium">Absent</div>
            <div class="text-2xl font-bold text-red-900">{{ number_format($summary['absent_count']) }}</div>
            <div class="text-xs text-red-600 mt-1">No attendance records</div>
        </div>
        <div class="rounded-xl border border-green-200 bg-green-50 p-4 shadow-sm cursor-pointer hover:shadow-md transition-shadow @if($issueType === 'overtime') ring-2 ring-green-500 @endif" wire:click="$set('issueType', 'overtime')">
            <div class="text-xs text-green-700 font-medium">Overtime</div>
            <div class="text-2xl font-bold text-green-900">{{ number_format($summary['overtime_count']) }}</div>
            <div class="text-xs text-green-600 mt-1">{{ $summary['total_overtime_hours'] ?? '00h 00m' }} total</div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-3">
            <div>
                <label class="text-xs text-gray-700">Date from</label>
                <input type="date" wire:model.live="dateFrom" class="w-full text-gray-900 rounded-lg border-gray-200 focus:border-[#0f8b48] focus:ring-[#0f8b48] bg-white">
            </div>
            <div>
                <label class="text-xs text-gray-700">Date to</label>
                <input type="date" wire:model.live="dateTo" class="w-full text-gray-900 rounded-lg border-gray-200 focus:border-[#0f8b48] focus:ring-[#0f8b48] bg-white">
            </div>
            <div>
                <label class="text-xs text-gray-700">Department</label>
                <select wire:model.live="department" class="w-full text-gray-900 rounded-lg border-gray-200 focus:border-[#0f8b48] focus:ring-[#0f8b48] bg-white">
                    <option value="">All Departments</option>
                    @foreach($departments as $dept)
                        <option value="{{ $dept }}">{{ $dept }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-xs text-gray-700">Employee</label>
                <select wire:model.live="employee" class="w-full text-gray-900 rounded-lg border-gray-200 focus:border-[#0f8b48] focus:ring-[#0f8b48] bg-white">
                    <option value="">All Employees</option>
                    @foreach($employees as $emp)
                        <option value="{{ $emp->id }}">{{ $emp->full_name }} ({{ $emp->emp_code }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-xs text-gray-700">Issue Type</label>
                <select wire:model.live="issueType" class="w-full text-gray-900 rounded-lg border-gray-200 focus:border-[#0f8b48] focus:ring-[#0f8b48] bg-white">
                    <option value="all">All Issues</option>
                    <option value="late">Late Only</option>
                    <option value="undertime">Undertime Only</option>
                    <option value="absent">Absent Only</option>
                    <option value="overtime">Overtime Only</option>
                </select>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div>
                <label class="text-xs text-gray-700">Search (name, code)</label>
                <input type="text" wire:model.live.debounce.400ms="q" placeholder="Search employees" class="w-full text-gray-900 rounded-lg border-gray-200 focus:border-[#0f8b48] focus:ring-[#0f8b48] bg-white placeholder:text-gray-400">
            </div>
            <div class="flex items-end gap-2 flex-wrap">
                <button wire:click="applyQuickRange('today')" class="px-3 py-2 rounded-lg text-sm border @if($quickRange==='today') bg-[#0f8b48] text-white border-[#0f8b48] @else border-gray-200 text-gray-700 @endif">Today</button>
                <button wire:click="applyQuickRange('week')" class="px-3 py-2 rounded-lg text-sm border @if($quickRange==='week') bg-[#0f8b48] text-white border-[#0f8b48] @else border-gray-200 text-gray-700 @endif">This Week</button>
                <button wire:click="applyQuickRange('month')" class="px-3 py-2 rounded-lg text-sm border @if($quickRange==='month') bg-[#0f8b48] text-white border-[#0f8b48] @else border-gray-200 text-gray-700 @endif">This Month</button>
                <button wire:click="applyQuickRange('last30')" class="px-3 py-2 rounded-lg text-sm border @if($quickRange==='last30') bg-[#0f8b48] text-white border-[#0f8b48] @else border-gray-200 text-gray-700 @endif">Last 30</button>
                @if($issueType !== 'all')
                    <button wire:click="$set('issueType', 'all')" class="px-3 py-2 rounded-lg text-sm border border-gray-300 text-gray-600 bg-gray-100 hover:bg-gray-200">Clear Filter</button>
                @endif
            </div>
        </div>
    </div>

    {{-- Employee Work Hours Summary --}}
    <div class="rounded-xl border border-blue-200 bg-blue-50 shadow-sm overflow-hidden">
        <div class="px-4 py-3 bg-blue-100 border-b border-blue-200">
            <h2 class="text-lg font-semibold text-blue-900">Employee Work Hours Summary</h2>
            <p class="text-xs text-blue-700">Total work hours per employee for the selected period ({{ \Carbon\Carbon::parse($dateFrom)->format('M d, Y') }} - {{ \Carbon\Carbon::parse($dateTo)->format('M d, Y') }})</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-blue-100/50 text-blue-900">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium">Employee</th>
                        <th class="px-4 py-3 text-left font-medium">Department</th>
                        <th class="px-4 py-3 text-center font-medium">Days Present</th>
                        <th class="px-4 py-3 text-center font-medium">Days Absent</th>
                        <th class="px-4 py-3 text-center font-medium">Total Work Hours</th>
                        <th class="px-4 py-3 text-center font-medium">Expected Hours</th>
                        <th class="px-4 py-3 text-center font-medium">Late</th>
                        <th class="px-4 py-3 text-center font-medium">Undertime</th>
                        <th class="px-4 py-3 text-center font-medium">Overtime</th>
                        <th class="px-4 py-3 text-center font-medium">Attendance Rate</th>
                        <th class="px-4 py-3 text-center font-medium">Efficiency</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-blue-100 bg-white">
                    @forelse($workHoursSummary as $empSummary)
                        <tr class="hover:bg-blue-50/50">
                            <td class="px-4 py-3">
                                <div class="font-medium text-gray-900">{{ $empSummary['employee_name'] }}</div>
                                <div class="text-xs text-gray-500">{{ $empSummary['emp_code'] }}</div>
                            </td>
                            <td class="px-4 py-3 text-gray-700">{{ $empSummary['department'] }}</td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    {{ $empSummary['days_present'] }} / {{ $empSummary['total_work_days'] }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if($empSummary['days_absent'] > 0)
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                        {{ $empSummary['days_absent'] }}
                                    </span>
                                @else
                                    <span class="text-gray-400">0</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="font-semibold text-blue-900">{{ $empSummary['total_worked_hours'] ?? '00h 00m' }}</span>
                            </td>
                            <td class="px-4 py-3 text-center text-gray-600">
                                {{ $empSummary['expected_hours'] ?? '00h 00m' }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if($empSummary['total_late_minutes'] > 0)
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-800">
                                        {{ $empSummary['total_late_hours'] }}
                                    </span>
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if($empSummary['total_undertime_minutes'] > 0)
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-orange-100 text-orange-800">
                                        {{ $empSummary['total_undertime_hours'] }}
                                    </span>
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if($empSummary['total_overtime_minutes'] > 0)
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        {{ $empSummary['total_overtime_hours'] }}
                                    </span>
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">
                                @php
                                    $rate = $empSummary['attendance_rate'];
                                    $rateColor = $rate >= 90 ? 'text-green-600' : ($rate >= 75 ? 'text-amber-600' : 'text-red-600');
                                @endphp
                                <span class="font-medium {{ $rateColor }}">{{ $rate }}%</span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                @php
                                    $eff = $empSummary['efficiency_rate'];
                                    $effColor = $eff >= 100 ? 'text-green-600' : ($eff >= 90 ? 'text-amber-600' : 'text-red-600');
                                @endphp
                                <span class="font-medium {{ $effColor }}">{{ $eff }}%</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="px-4 py-6 text-center text-gray-500">
                                No employees found for the selected filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if($workHoursSummary->isNotEmpty())
                <tfoot class="bg-blue-100/50 text-blue-900 font-medium">
                    <tr>
                        <td class="px-4 py-3" colspan="2">Total ({{ $workHoursSummary->count() }} employees)</td>
                        <td class="px-4 py-3 text-center">{{ $workHoursSummary->sum('days_present') }}</td>
                        <td class="px-4 py-3 text-center">{{ $workHoursSummary->sum('days_absent') }}</td>
                        <td class="px-4 py-3 text-center font-bold">
                            @php
                                $totalMinutes = $workHoursSummary->sum('total_worked_minutes');
                                $hours = intdiv($totalMinutes, 60);
                                $mins = $totalMinutes % 60;
                            @endphp
                            {{ sprintf('%02dh %02dm', $hours, $mins) }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            @php
                                $totalExpected = $workHoursSummary->sum('expected_minutes');
                                $expHours = intdiv($totalExpected, 60);
                                $expMins = $totalExpected % 60;
                            @endphp
                            {{ sprintf('%02dh %02dm', $expHours, $expMins) }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            @php
                                $totalLate = $workHoursSummary->sum('total_late_minutes');
                                $lateH = intdiv($totalLate, 60);
                                $lateM = $totalLate % 60;
                            @endphp
                            {{ sprintf('%02dh %02dm', $lateH, $lateM) }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            @php
                                $totalUnder = $workHoursSummary->sum('total_undertime_minutes');
                                $underH = intdiv($totalUnder, 60);
                                $underM = $totalUnder % 60;
                            @endphp
                            {{ sprintf('%02dh %02dm', $underH, $underM) }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            @php
                                $totalOver = $workHoursSummary->sum('total_overtime_minutes');
                                $overH = intdiv($totalOver, 60);
                                $overM = $totalOver % 60;
                            @endphp
                            {{ sprintf('%02dh %02dm', $overH, $overM) }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            {{ $workHoursSummary->count() > 0 ? round($workHoursSummary->avg('attendance_rate'), 1) : 0 }}%
                        </td>
                        <td class="px-4 py-3 text-center">
                            {{ $workHoursSummary->count() > 0 ? round($workHoursSummary->avg('efficiency_rate'), 1) : 0 }}%
                        </td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>

    {{-- Records table --}}
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-gray-700">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium">Date</th>
                        <th class="px-4 py-3 text-left font-medium">Employee</th>
                        <th class="px-4 py-3 text-left font-medium">Department</th>
                        <th class="px-4 py-3 text-left font-medium">Time In</th>
                        <th class="px-4 py-3 text-left font-medium">Time Out</th>
                        <th class="px-4 py-3 text-left font-medium">Expected</th>
                        <th class="px-4 py-3 text-left font-medium">Actual</th>
                        <th class="px-4 py-3 text-center font-medium">Late</th>
                        <th class="px-4 py-3 text-center font-medium">Undertime</th>
                        <th class="px-4 py-3 text-center font-medium">Overtime</th>
                        <th class="px-4 py-3 text-left font-medium">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($records as $record)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-gray-900 whitespace-nowrap">{{ $record['date_label'] }}</td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-gray-900">{{ $record['employee_name'] }}</div>
                                <div class="text-xs text-gray-500">{{ $record['emp_code'] }}</div>
                            </td>
                            <td class="px-4 py-3 text-gray-700">{{ $record['department'] }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $record['time_in'] ?? '-' }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $record['time_out'] ?? '-' }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $record['expected_hours'] }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $record['actual_hours'] ?? '-' }}</td>
                            <td class="px-4 py-3 text-center">
                                @if($record['late_minutes'] > 0)
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-800">
                                        {{ number_format($record['late_minutes'], 2) }}m
                                    </span>
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if($record['undertime_minutes'] > 0)
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-orange-100 text-orange-800">
                                        {{ number_format($record['undertime_minutes'], 2) }}m
                                    </span>
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if($record['overtime_minutes'] > 0)
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        {{ number_format($record['overtime_minutes'], 2) }}m
                                    </span>
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @php
                                    $statusColors = [
                                        'late' => 'bg-amber-100 text-amber-800',
                                        'undertime' => 'bg-orange-100 text-orange-800',
                                        'absent' => 'bg-red-100 text-red-800',
                                        'overtime' => 'bg-green-100 text-green-800',
                                        'present' => 'bg-gray-100 text-gray-800',
                                    ];
                                    $statusColor = $statusColors[$record['issue_type']] ?? 'bg-gray-100 text-gray-800';
                                @endphp
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium {{ $statusColor }}">
                                    {{ $record['status'] }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="px-4 py-8 text-center text-gray-500">
                                <div class="flex flex-col items-center">
                                    <svg class="w-12 h-12 text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <p class="text-lg font-medium">No attendance issues found</p>
                                    <p class="text-sm">All employees have met their expected work hours for the selected period.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Legend --}}
    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
        <h3 class="text-sm font-medium text-gray-700 mb-3">Legend</h3>
        <div class="flex flex-wrap gap-4 text-xs">
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center px-2 py-1 rounded-full bg-amber-100 text-amber-800">Late</span>
                <span class="text-gray-600">Arrived after the scheduled time-in</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center px-2 py-1 rounded-full bg-orange-100 text-orange-800">Undertime</span>
                <span class="text-gray-600">Worked less than expected hours</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center px-2 py-1 rounded-full bg-red-100 text-red-800">Absent</span>
                <span class="text-gray-600">No attendance record for the day</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center px-2 py-1 rounded-full bg-green-100 text-green-800">Overtime</span>
                <span class="text-gray-600">Worked more than expected hours</span>
            </div>
        </div>
    </div>
</div>
