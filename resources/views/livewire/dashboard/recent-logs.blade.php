<div class="bg-white rounded-2xl p-4 shadow border border-[rgba(0,0,0,0.06)]">
    <h2 class="font-semibold mb-3 text-gray-700">Recent Attendance Logs</h2>
    <table class="w-full text-sm text-left text-gray-700">
        <thead class="text-xs uppercase bg-gray-100">
            <tr>
                <th class="px-3 py-2">Employee</th>
                <th class="px-3 py-2">Status</th>
                <th class="px-3 py-2">Time</th>
            </tr>
        </thead>
        <tbody>
            @forelse($logs as $log)
                <tr class="border-b border-gray-100">
                    <td class="px-3 py-2">
                        {{ optional($log->employee)->full_name ?? ($log->emp_code ?: 'Unknown') }}
                    </td>
                    <td class="px-3 py-2">
                        @php($action = strtolower((string)($log->action ?? '')))
                        {{ in_array($action, ['time_in','check_in','in']) ? 'Checked In' : (in_array($action, ['time_out','check_out','out']) ? 'Checked Out' : strtoupper($action ?: 'N/A')) }}
                    </td>
                    <td class="px-3 py-2">{{ ($log->logged_at ?? $log->created_at)->format('h:i A') }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="px-3 py-3 text-center">No recent logs</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
