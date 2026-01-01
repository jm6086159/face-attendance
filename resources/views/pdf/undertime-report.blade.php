<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Undertime Report</title>
    <style>
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 10px;
            color: #1f2937;
            margin: 0;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #0f8b48;
            padding-bottom: 15px;
        }
        .header h1 {
            color: #0f8b48;
            margin: 0 0 5px 0;
            font-size: 18px;
        }
        .header p {
            margin: 0;
            color: #6b7280;
            font-size: 11px;
        }
        .filters {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 5px;
            padding: 10px;
            margin-bottom: 15px;
        }
        .filters-title {
            font-weight: bold;
            color: #374151;
            margin-bottom: 5px;
        }
        .filters-content {
            display: flex;
            gap: 15px;
        }
        .filter-item {
            display: inline;
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
            margin-bottom: 15px;
        }
        .summary-box {
            display: table-cell;
            width: 25%;
            text-align: center;
            padding: 10px;
            border: 1px solid #e5e7eb;
            background: #f9fafb;
        }
        .summary-box.danger {
            background: #fef2f2;
            border-color: #fecaca;
        }
        .summary-box.warning {
            background: #fffbeb;
            border-color: #fde68a;
        }
        .summary-label {
            color: #6b7280;
            font-size: 9px;
            text-transform: uppercase;
        }
        .summary-value {
            font-size: 16px;
            font-weight: bold;
            color: #1f2937;
        }
        .summary-box.danger .summary-value {
            color: #dc2626;
        }
        .summary-box.warning .summary-value {
            color: #d97706;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        th {
            background: #0f8b48;
            color: white;
            padding: 8px 6px;
            text-align: left;
            font-size: 9px;
            text-transform: uppercase;
        }
        td {
            padding: 6px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 9px;
        }
        tr:nth-child(even) {
            background: #f9fafb;
        }
        .undertime-badge {
            background: #fef2f2;
            color: #dc2626;
            padding: 2px 6px;
            border-radius: 10px;
            font-weight: 500;
        }
        .status-undertime {
            background: #fffbeb;
            color: #d97706;
            padding: 2px 6px;
            border-radius: 10px;
        }
        .status-incomplete {
            background: #fef2f2;
            color: #dc2626;
            padding: 2px 6px;
            border-radius: 10px;
        }
        .status-absent {
            background: #f3f4f6;
            color: #6b7280;
            padding: 2px 6px;
            border-radius: 10px;
        }
        .footer {
            margin-top: 20px;
            text-align: center;
            color: #9ca3af;
            font-size: 9px;
            border-top: 1px solid #e5e7eb;
            padding-top: 10px;
        }
        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Undertime Report</h1>
        <p>Generated on {{ now()->format('F d, Y h:i A') }}</p>
    </div>

    <div class="filters">
        <div class="filters-title">Applied Filters</div>
        <div class="filters-content">
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
            @if(!empty($filters['employee']))
                <span class="filter-item">
                    <span class="filter-label">Employee ID:</span>
                    <span class="filter-value">{{ $filters['employee'] }}</span>
                </span>
            @endif
        </div>
    </div>

    <div class="summary">
        <div class="summary-box">
            <div class="summary-label">Total Records</div>
            <div class="summary-value">{{ number_format($summary['total_records']) }}</div>
        </div>
        <div class="summary-box">
            <div class="summary-label">Employees Affected</div>
            <div class="summary-value">{{ number_format($summary['unique_employees']) }}</div>
        </div>
        <div class="summary-box danger">
            <div class="summary-label">Total Undertime</div>
            <div class="summary-value">{{ $summary['total_undertime_hours'] ?? '0h 0m' }}</div>
        </div>
        <div class="summary-box warning">
            <div class="summary-label">Avg. Undertime</div>
            <div class="summary-value">{{ $summary['avg_undertime_hours'] ?? '0h 0m' }}</div>
        </div>
    </div>

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
                <th>Undertime</th>
                <th>Status</th>
                <th>Remarks</th>
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
                    <td>{{ $record['actual_hours'] }}</td>
                    <td><span class="undertime-badge">{{ $record['undertime_minutes'] }} min</span></td>
                    <td>
                        @php
                            $statusClass = match($record['status']) {
                                'Undertime' => 'status-undertime',
                                'Incomplete' => 'status-incomplete',
                                'Absent' => 'status-absent',
                                default => ''
                            };
                        @endphp
                        <span class="{{ $statusClass }}">{{ $record['status'] }}</span>
                    </td>
                    <td>{{ $record['remarks'] ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="11" style="text-align: center; padding: 20px;">No undertime records found for the selected period.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        <p>This report was automatically generated by the Face Attendance System.</p>
        <p>Total Records: {{ $records->count() }} | Report ID: UT-{{ now()->format('YmdHis') }}</p>
    </div>
</body>
</html>
