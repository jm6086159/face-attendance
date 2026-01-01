<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Attendance Issues Report</title>
    <style>
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 9px;
            color: #1f2937;
            margin: 0;
            padding: 15px;
        }
        .header {
            text-align: center;
            margin-bottom: 15px;
            border-bottom: 2px solid #0f8b48;
            padding-bottom: 10px;
        }
        .header h1 {
            color: #0f8b48;
            margin: 0 0 5px 0;
            font-size: 16px;
        }
        .header p {
            margin: 0;
            color: #6b7280;
            font-size: 10px;
        }
        .filters {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 5px;
            padding: 8px;
            margin-bottom: 12px;
            font-size: 9px;
        }
        .filters-title {
            font-weight: bold;
            color: #374151;
            margin-bottom: 3px;
        }
        .filter-item {
            display: inline;
            margin-right: 15px;
        }
        .filter-label {
            color: #6b7280;
        }
        .filter-value {
            color: #1f2937;
            font-weight: 500;
        }
        .summary {
            display: table;
            width: 100%;
            margin-bottom: 12px;
        }
        .summary-box {
            display: table-cell;
            width: 25%;
            text-align: center;
            padding: 8px;
            border: 1px solid #e5e7eb;
        }
        .summary-box.late {
            background: #fffbeb;
            border-color: #fde68a;
        }
        .summary-box.undertime {
            background: #fff7ed;
            border-color: #fed7aa;
        }
        .summary-box.absent {
            background: #fef2f2;
            border-color: #fecaca;
        }
        .summary-box.overtime {
            background: #f0fdf4;
            border-color: #bbf7d0;
        }
        .summary-label {
            color: #6b7280;
            font-size: 8px;
            text-transform: uppercase;
        }
        .summary-value {
            font-size: 14px;
            font-weight: bold;
            color: #1f2937;
        }
        .summary-box.late .summary-value { color: #d97706; }
        .summary-box.undertime .summary-value { color: #ea580c; }
        .summary-box.absent .summary-value { color: #dc2626; }
        .summary-box.overtime .summary-value { color: #16a34a; }
        .summary-hours {
            font-size: 8px;
            color: #6b7280;
        }
        .section-title {
            background: #0f8b48;
            color: white;
            padding: 6px 10px;
            font-size: 10px;
            font-weight: bold;
            margin-top: 15px;
            margin-bottom: 8px;
        }
        .work-hours-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .work-hours-table th {
            background: #1e40af;
            color: white;
            padding: 5px 3px;
            text-align: center;
            font-size: 7px;
        }
        .work-hours-table td {
            padding: 4px 3px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 7px;
            text-align: center;
        }
        .work-hours-table tr:nth-child(even) {
            background: #f0f9ff;
        }
        .work-hours-table tfoot td {
            background: #dbeafe;
            font-weight: bold;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th {
            background: #0f8b48;
            color: white;
            padding: 6px 4px;
            text-align: left;
            font-size: 8px;
            text-transform: uppercase;
        }
        td {
            padding: 5px 4px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 8px;
        }
        tr:nth-child(even) {
            background: #f9fafb;
        }
        .badge {
            padding: 2px 5px;
            border-radius: 8px;
            font-weight: 500;
            font-size: 7px;
        }
        .badge-late { background: #fffbeb; color: #d97706; }
        .badge-undertime { background: #fff7ed; color: #ea580c; }
        .badge-absent { background: #fef2f2; color: #dc2626; }
        .badge-overtime { background: #f0fdf4; color: #16a34a; }
        .text-center { text-align: center; }
        .footer {
            margin-top: 15px;
            text-align: center;
            color: #9ca3af;
            font-size: 8px;
            border-top: 1px solid #e5e7eb;
            padding-top: 8px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Attendance Issues Report</h1>
        <p>Generated on {{ now()->format('F d, Y h:i A') }}</p>
    </div>

    <div class="filters">
        <div class="filters-title">Applied Filters</div>
        <span class="filter-item">
            <span class="filter-label">Date Range:</span>
            <span class="filter-value">{{ $filters['date_from'] ?? 'N/A' }} to {{ $filters['date_to'] ?? 'N/A' }}</span>
        </span>
        @if(!empty($filters['department']))
            <span class="filter-item">
                <span class="filter-label">Department:</span>
                <span class="filter-value">{{ $filters['department'] }}</span>
            </span>
        @endif
        @if(!empty($filters['issue_type']) && $filters['issue_type'] !== 'all')
            <span class="filter-item">
                <span class="filter-label">Issue Type:</span>
                <span class="filter-value">{{ ucfirst($filters['issue_type']) }}</span>
            </span>
        @endif
    </div>

    <div class="summary">
        <div class="summary-box late">
            <div class="summary-label">Late</div>
            <div class="summary-value">{{ number_format($summary['late_count']) }}</div>
            <div class="summary-hours">{{ $summary['total_late_hours'] ?? '0h 0m' }}</div>
        </div>
        <div class="summary-box undertime">
            <div class="summary-label">Undertime</div>
            <div class="summary-value">{{ number_format($summary['undertime_count']) }}</div>
            <div class="summary-hours">{{ $summary['total_undertime_hours'] ?? '0h 0m' }}</div>
        </div>
        <div class="summary-box absent">
            <div class="summary-label">Absent</div>
            <div class="summary-value">{{ number_format($summary['absent_count']) }}</div>
            <div class="summary-hours">No records</div>
        </div>
        <div class="summary-box overtime">
            <div class="summary-label">Overtime</div>
            <div class="summary-value">{{ number_format($summary['overtime_count']) }}</div>
            <div class="summary-hours">{{ $summary['total_overtime_hours'] ?? '0h 0m' }}</div>
        </div>
    </div>

    {{-- Employee Work Hours Summary --}}
    @if(isset($workHoursSummary) && $workHoursSummary->count() > 0)
    <div class="section-title" style="background: #1e40af;">Employee Work Hours Summary</div>
    <table class="work-hours-table">
        <thead>
            <tr>
                <th style="text-align: left;">Employee</th>
                <th style="text-align: left;">Dept</th>
                <th>Present</th>
                <th>Absent</th>
                <th>Total Hours</th>
                <th>Expected</th>
                <th>Late</th>
                <th>Undertime</th>
                <th>Overtime</th>
                <th>Attendance %</th>
                <th>Efficiency %</th>
            </tr>
        </thead>
        <tbody>
            @foreach($workHoursSummary as $emp)
            <tr>
                <td style="text-align: left;">{{ $emp['employee_name'] }}<br><small style="color:#6b7280;">{{ $emp['emp_code'] }}</small></td>
                <td style="text-align: left;">{{ $emp['department'] }}</td>
                <td>{{ $emp['days_present'] }}/{{ $emp['total_work_days'] }}</td>
                <td>{{ $emp['days_absent'] }}</td>
                <td style="font-weight: bold;">{{ $emp['total_worked_hours'] ?? '00h 00m' }}</td>
                <td>{{ $emp['expected_hours'] ?? '00h 00m' }}</td>
                <td>{{ $emp['total_late_hours'] ?? '-' }}</td>
                <td>{{ $emp['total_undertime_hours'] ?? '-' }}</td>
                <td>{{ $emp['total_overtime_hours'] ?? '-' }}</td>
                <td>{{ $emp['attendance_rate'] }}%</td>
                <td>{{ $emp['efficiency_rate'] }}%</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td style="text-align: left;" colspan="2"><strong>Total ({{ $workHoursSummary->count() }} employees)</strong></td>
                <td>{{ $workHoursSummary->sum('days_present') }}</td>
                <td>{{ $workHoursSummary->sum('days_absent') }}</td>
                <td style="font-weight: bold;">
                    @php
                        $totalMinutes = $workHoursSummary->sum('total_worked_minutes');
                        $hours = intdiv($totalMinutes, 60);
                        $mins = $totalMinutes % 60;
                    @endphp
                    {{ sprintf('%02dh %02dm', $hours, $mins) }}
                </td>
                <td>
                    @php
                        $totalExpected = $workHoursSummary->sum('expected_minutes');
                        $expHours = intdiv($totalExpected, 60);
                        $expMins = $totalExpected % 60;
                    @endphp
                    {{ sprintf('%02dh %02dm', $expHours, $expMins) }}
                </td>
                <td>
                    @php
                        $totalLate = $workHoursSummary->sum('total_late_minutes');
                    @endphp
                    {{ sprintf('%02dh %02dm', intdiv($totalLate, 60), $totalLate % 60) }}
                </td>
                <td>
                    @php
                        $totalUnder = $workHoursSummary->sum('total_undertime_minutes');
                    @endphp
                    {{ sprintf('%02dh %02dm', intdiv($totalUnder, 60), $totalUnder % 60) }}
                </td>
                <td>
                    @php
                        $totalOver = $workHoursSummary->sum('total_overtime_minutes');
                    @endphp
                    {{ sprintf('%02dh %02dm', intdiv($totalOver, 60), $totalOver % 60) }}
                </td>
                <td>{{ round($workHoursSummary->avg('attendance_rate'), 1) }}%</td>
                <td>{{ round($workHoursSummary->avg('efficiency_rate'), 1) }}%</td>
            </tr>
        </tfoot>
    </table>
    @endif

    <div class="section-title">Detailed Attendance Records</div>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Employee</th>
                <th>Code</th>
                <th>Department</th>
                <th>Time In</th>
                <th>Time Out</th>
                <th>Expected</th>
                <th>Actual</th>
                <th class="text-center">Late</th>
                <th class="text-center">UT</th>
                <th class="text-center">OT</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($records as $record)
                <tr>
                    <td>{{ $record['date'] }}</td>
                    <td>{{ $record['employee_name'] }}</td>
                    <td>{{ $record['emp_code'] }}</td>
                    <td>{{ $record['department'] }}</td>
                    <td>{{ $record['time_in'] ?? '-' }}</td>
                    <td>{{ $record['time_out'] ?? '-' }}</td>
                    <td>{{ $record['expected_hours'] }}</td>
                    <td>{{ $record['actual_hours'] ?? '-' }}</td>
                    <td class="text-center">
                        @if($record['late_minutes'] > 0)
                            <span class="badge badge-late">{{ $record['late_minutes'] }}m</span>
                        @else
                            -
                        @endif
                    </td>
                    <td class="text-center">
                        @if($record['undertime_minutes'] > 0)
                            <span class="badge badge-undertime">{{ $record['undertime_minutes'] }}m</span>
                        @else
                            -
                        @endif
                    </td>
                    <td class="text-center">
                        @if($record['overtime_minutes'] > 0)
                            <span class="badge badge-overtime">{{ $record['overtime_minutes'] }}m</span>
                        @else
                            -
                        @endif
                    </td>
                    <td>
                        @php
                            $badgeClass = match($record['issue_type']) {
                                'late' => 'badge-late',
                                'undertime' => 'badge-undertime',
                                'absent' => 'badge-absent',
                                'overtime' => 'badge-overtime',
                                default => ''
                            };
                        @endphp
                        <span class="badge {{ $badgeClass }}">{{ $record['status'] }}</span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="12" style="text-align: center; padding: 20px;">No attendance issues found for the selected period.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        <p>This report was automatically generated by the Face Attendance System.</p>
        <p>Total Records: {{ $records->count() }} | Report ID: AI-{{ now()->format('YmdHis') }}</p>
    </div>
</body>
</html>
