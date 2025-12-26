<div class="p-6 space-y-6 text-gray-900">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold text-white">Attendance History</h1>
            <p class="text-sm text-white/80">Review attendance trends, apply quick filters, and export reports.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <button wire:click="exportExcel" class="px-3 py-2 rounded-lg text-sm text-white" style="background:#0f8b48;">Export Excel</button>
            <button wire:click="exportPdf" class="px-3 py-2 rounded-lg text-sm border border-gray-200 text-gray-700 bg-white">Export PDF</button>
        </div>
    </div>

    {{-- Filters --}}
    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
            <div>
                <label class="text-xs text-gray-700">Date from</label>
                <input type="date" wire:model="dateFrom" class="w-full text-gray-900 rounded-lg border-gray-200 focus:border-[#0f8b48] focus:ring-[#0f8b48] bg-white">
            </div>
            <div>
                <label class="text-xs text-gray-700">Date to</label>
                <input type="date" wire:model="dateTo" class="w-full text-gray-900 rounded-lg border-gray-200 focus:border-[#0f8b48] focus:ring-[#0f8b48] bg-white">
            </div>
            <div>
                <label class="text-xs text-gray-700">Department</label>
                <select wire:model="department" class="w-full text-gray-900 rounded-lg border-gray-200 focus:border-[#0f8b48] focus:ring-[#0f8b48] bg-white">
                    <option value="">All Departments</option>
                    @foreach($departments as $dept)
                        <option value="{{ $dept }}">{{ $dept }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-xs text-gray-700">Employee</label>
                <select wire:model="employee" class="w-full text-gray-900 rounded-lg border-gray-200 focus:border-[#0f8b48] focus:ring-[#0f8b48] bg-white">
                    <option value="">All Employees</option>
                    @foreach($employees as $emp)
                        @php
                            $displayName = method_exists($emp, 'getFullNameAttribute') ? $emp->full_name : trim($emp->first_name.' '.$emp->last_name);
                        @endphp
                        <option value="{{ $emp->id }}">{{ $displayName }} ({{ $emp->emp_code }})</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
            <div>
                <label class="text-xs text-gray-700">Status</label>
                <select wire:model="status" class="w-full text-gray-900 rounded-lg border-gray-200 focus:border-[#0f8b48] focus:ring-[#0f8b48] bg-white">
                    <option value="all">All</option>
                    <option value="present">Present</option>
                    <option value="late">Late</option>
                    <option value="missing_out">Missing Time Out</option>
                    <option value="absent">Absent</option>
                </select>
            </div>
            <div>
                <label class="text-xs text-gray-700">Search (name, code)</label>
                <input type="text" wire:model.debounce.400ms="q" placeholder="Search employees" class="w-full text-gray-900 rounded-lg border-gray-200 focus:border-[#0f8b48] focus:ring-[#0f8b48] bg-white placeholder:text-gray-400">
            </div>
            <div class="flex items-end gap-2">
                <button wire:click="applyQuickRange('today')" class="px-3 py-2 rounded-lg text-sm border @if($quickRange==='today') bg-[#0f8b48] text-white border-[#0f8b48] @else border-gray-200 text-gray-700 @endif">Today</button>
                <button wire:click="applyQuickRange('week')" class="px-3 py-2 rounded-lg text-sm border @if($quickRange==='week') bg-[#0f8b48] text-white border-[#0f8b48] @else border-gray-200 text-gray-700 @endif">This Week</button>
                <button wire:click="applyQuickRange('month')" class="px-3 py-2 rounded-lg text-sm border @if($quickRange==='month') bg-[#0f8b48] text-white border-[#0f8b48] @else border-gray-200 text-gray-700 @endif">This Month</button>
                <button wire:click="applyQuickRange('last30')" class="px-3 py-2 rounded-lg text-sm border @if($quickRange==='last30') bg-[#0f8b48] text-white border-[#0f8b48] @else border-gray-200 text-gray-700 @endif">Last 30</button>
            </div>
        </div>
    </div>

    {{-- Summary cards --}}
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <div class="text-xs text-gray-700">Total Days</div>
            <div class="text-2xl font-semibold text-gray-900">{{ number_format($summary['days']) }}</div>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <div class="text-xs text-gray-700">Present</div>
            <div class="text-2xl font-semibold text-gray-900">{{ number_format($summary['present']) }}</div>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <div class="text-xs text-gray-700">Absent</div>
            <div class="text-2xl font-semibold text-gray-900">{{ number_format($summary['absent']) }}</div>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <div class="text-xs text-gray-700">Late</div>
            <div class="text-2xl font-semibold text-gray-900">{{ number_format($summary['late']) }}</div>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <div class="text-xs text-gray-700">On Leave</div>
            <div class="text-2xl font-semibold text-gray-900">{{ number_format($summary['leave']) }}</div>
        </div>
    </div>

    {{-- History table --}}
    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
        <div class="flex items-center justify-between mb-3 text-gray-900">
            <h3 class="text-lg font-semibold text-gray-900">Detailed Logs</h3>
            <div class="text-sm text-gray-700">Showing {{ $records->count() }} entries</div>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm text-left text-gray-900">
                <thead class="bg-gray-50 text-xs uppercase text-gray-600">
                    <tr>
                        <th class="px-3 py-2">Date</th>
                        <th class="px-3 py-2">Employee</th>
                        <th class="px-3 py-2">Department</th>
                        <th class="px-3 py-2">Check-in</th>
                        <th class="px-3 py-2">Check-out</th>
                        <th class="px-3 py-2">Total Hours</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2">Remarks</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($records as $row)
                        <tr>
                            <td class="px-3 py-2 whitespace-nowrap">{{ $row['date_label'] }}</td>
                            <td class="px-3 py-2">
                                <div class="font-semibold text-gray-900">{{ $row['employee_name'] }}</div>
                                <div class="text-xs text-gray-600">{{ $row['emp_code'] }}</div>
                            </td>
                            <td class="px-3 py-2">{{ $row['department'] }}</td>
                            <td class="px-3 py-2">{{ $row['time_in'] ?? '--' }}</td>
                            <td class="px-3 py-2">{{ $row['time_out'] ?? '--' }}</td>
                            <td class="px-3 py-2">{{ $row['total_duration'] ?? '--' }}</td>
                            <td class="px-3 py-2">
                                @php
                                    $badge = match($row['status']) {
                                        'Late' => 'bg-red-100 text-red-700',
                                        'Incomplete' => 'bg-amber-100 text-amber-700',
                                        'Absent' => 'bg-gray-100 text-gray-700',
                                        default => 'bg-green-100 text-green-700',
                                    };
                                @endphp
                                <span class="px-2 py-1 rounded-full text-xs font-medium {{ $badge }}">{{ $row['status'] }}</span>
                            </td>
                            <td class="px-3 py-2 text-gray-700">{{ $row['remarks'] ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-3 py-4 text-center text-gray-600">No attendance history for the selected filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
