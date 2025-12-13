<div class="bg-white rounded-2xl p-4 shadow border border-[rgba(0,0,0,0.06)]">
    <h2 class="font-semibold mb-3 text-gray-700">Employees by Department</h2>

    <div class="h-64" wire:ignore>
        <canvas id="deptChart" class="w-full h-full"></canvas>
    </div>

    @once
        @push('scripts')
            <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        @endpush
    @endonce

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const renderDeptChart = () => {
                const ctx = document.getElementById('deptChart')?.getContext('2d');
                if (!ctx || typeof Chart === 'undefined') return;

                if (window.__deptChart) {
                    window.__deptChart.destroy();
                }

                window.__deptChart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: @json($labels),
                        datasets: [{
                            label: 'Employees',
                            data: @json($values),
                            backgroundColor: '#22c55e',
                            borderColor: '#16a34a',
                            borderWidth: 1,
                        }],
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

            renderDeptChart();
        });
    </script>
</div>
