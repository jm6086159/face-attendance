<div class="bg-white rounded-2xl p-4 shadow border border-[rgba(0,0,0,0.06)]">
    <h2 class="font-semibold mb-3 text-gray-700">Attendance Trend (This Week)</h2>
    <div class="h-64" wire:ignore>
        <canvas id="attendanceChart" class="w-full h-full"></canvas>
    </div>

    @once
        @push('scripts')
            <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        @endpush
    @endonce

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const renderAttendanceChart = () => {
                const canvas = document.getElementById('attendanceChart');
                if (!canvas || typeof Chart === 'undefined') return;

                const ctx = canvas.getContext('2d');
                if (window.__attendanceChart) {
                    window.__attendanceChart.destroy();
                }

                window.__attendanceChart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: @json($chartData['labels']),
                        datasets: [{
                            label: 'Employees Present',
                            data: @json($chartData['values']),
                            borderColor: '#3b82f6',
                            backgroundColor: 'rgba(59,130,246,0.15)',
                            fill: true,
                            tension: 0.3,
                            pointRadius: 4,
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            y: { beginAtZero: true, ticks: { precision: 0 } },
                            x: { grid: { display: false } }
                        }
                    }
                });
            };

            renderAttendanceChart();
        });
    </script>
</div>
