<div class="p-6 space-y-4">
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold">Attendance Logs</h1>
    </div>

    <div class="bg-white dark:bg-neutral-900 rounded-lg shadow p-4 space-y-4 text-black dark:text-gray-100">
        <div class="flex flex-wrap items-center gap-2">
            <input type="text" wire:model.debounce.300ms="q" placeholder="Search code/name" class="border rounded px-3 py-2 w-64 dark:bg-neutral-800 dark:border-neutral-700">
            <input type="date" wire:model="dateFrom" class="border rounded px-3 py-2 dark:bg-neutral-800 dark:border-neutral-700">
            <input type="date" wire:model="dateTo" class="border rounded px-3 py-2 dark:bg-neutral-800 dark:border-neutral-700">
            <div class="flex flex-wrap items-center gap-2"
                data-dependent-dropdown
                data-endpoint="{{ route('departments.employees', ['department' => '__DEPARTMENT__']) }}"
                data-selected-employee="{{ $selectedEmployee ?? '' }}">
                <select data-role="department-select" wire:model="selectedDepartment" class="border rounded px-2 py-2 dark:bg-neutral-800 dark:border-neutral-700">
                    <option value="">All Departments</option>
                    @foreach ($departments as $dept)
                        <option value="{{ $dept }}">{{ $dept }}</option>
                    @endforeach
                </select>
                <div class="flex flex-col">
                    <select data-role="employee-select" class="border rounded px-2 py-2 dark:bg-neutral-800 dark:border-neutral-700">
                        <option value="">All Employees</option>
                        @foreach ($employees as $employee)
                            <option value="{{ $employee->id }}">
                                {{ $employee->emp_code ?? 'N/A' }} ({{ $employee->full_name }})
                            </option>
                        @endforeach
                    </select>
                    <input type="hidden" data-role="employee-value" wire:model.live="selectedEmployee" value="{{ $selectedEmployee ?? '' }}">
                    <p class="text-xs text-gray-500 dark:text-gray-400 pt-1" data-role="feedback"></p>
                </div>
            </div>
            <select wire:model="selectedAction" class="border rounded px-2 py-2 dark:bg-neutral-800 dark:border-neutral-700">
                <option value="">All Actions</option>
                <option value="time_in">Time In</option>
                <option value="time_out">Time Out</option>
            </select>
            <label class="inline-flex items-center gap-1 text-sm">
                <input type="checkbox" wire:model="lateOnly" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                Late only
            </label>
            <select wire:model="perPage" class="border rounded px-2 py-2 dark:bg-neutral-800 dark:border-neutral-700">
                <option value="10">10</option>
                <option value="15">15</option>
                <option value="30">30</option>
            </select>
            <button wire:click="clearFilters" class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-md border border-gray-300 text-gray-700 hover:bg-gray-50 dark:border-neutral-700 dark:text-neutral-200 dark:hover:bg-neutral-800">
                Reset
            </button>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <button wire:click="exportExcel" class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-md bg-green-600 text-white hover:bg-green-700">
                Export Detailed XLSX
            </button>
            <button wire:click="exportSummaryCsv" class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-md border border-gray-300 text-gray-700 hover:bg-gray-50 dark:border-neutral-700 dark:text-neutral-200 dark:hover:bg-neutral-800">
                Export Summary CSV
            </button>
            <button wire:click="exportSummaryPdf" class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-md bg-red-600 text-white hover:bg-red-700">
                Export Summary PDF
            </button>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="sticky top-0 z-10">
                    <tr class="bg-gray-100 dark:bg-neutral-800 text-left">
                        <th class="p-2">ID</th>
                        <th class="p-2">Employee</th>
                        <th class="p-2">Action</th>
                        <th class="p-2">Time</th>
                        <th class="p-2">Confidence</th>
                        <th class="p-2">Model</th>
                        <th class="p-2">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr class="border-b hover:bg-gray-50 dark:hover:bg-neutral-800 dark:border-neutral-700">
                            <td class="p-2">{{ $log->id }}</td>
                            <td class="p-2">{{ $log->emp_code ?? '-' }} @if ($log->employee) ({{ $log->employee->full_name }}) @endif</td>
                            <td class="p-2">
                                @if($log->action === 'time_in' && $log->is_late)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800">
                                        time_in/late
                                    </span>
                                @elseif($log->action === 'time_in')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        time_in
                                    </span>
                                @elseif($log->action === 'time_out')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        time_out
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                        {{ $log->action }}
                                    </span>
                                @endif
                            </td>
                            <td class="p-2">{{ $log->logged_at->format('Y-m-d H:i:s') }}</td>
                            <td class="p-2">{{ $log->confidence ? number_format($log->confidence, 4) : '-' }}</td>
                            <td class="p-2">{{ $log->meta['model'] ?? '-' }}</td>
                            <td class="p-2">
                                <button class="text-red-600 hover:underline"
                                    wire:click="delete({{ $log->id }})"
                                    onclick="return confirm('Delete this attendance log (ID {{ $log->id }})?');">
                                    Delete
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="p-6 text-center text-gray-500">No logs yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $logs->links() }}</div>
    </div>
</div>

@once
    <script>
        (function () {
            const initDependentDropdowns = () => {
                document.querySelectorAll('[data-dependent-dropdown]').forEach((container) => {
                    if (container.dataset.dependentInitialized === 'true') {
                        return;
                    }

                    const departmentSelect = container.querySelector('[data-role="department-select"]');
                    const employeeSelect = container.querySelector('[data-role="employee-select"]');
                    const hiddenInput = container.querySelector('[data-role="employee-value"]');
                    const feedback = container.querySelector('[data-role="feedback"]');
                    const endpointTemplate = container.dataset.endpoint;

                    if (!departmentSelect || !employeeSelect || !hiddenInput || !endpointTemplate) {
                        return;
                    }

                    const setValue = (value) => {
                        const normalized = value ?? '';
                        if (employeeSelect.value !== normalized) {
                            employeeSelect.value = normalized;
                        }
                        if (hiddenInput.value !== normalized) {
                            hiddenInput.value = normalized;
                            hiddenInput.dispatchEvent(new Event('input', { bubbles: true }));
                        }
                    };

                    const populateEmployees = (rows) => {
                        const currentSelection = container.dataset.selectedEmployee || hiddenInput.value || '';
                        employeeSelect.innerHTML = '<option value="">All Employees</option>';

                        rows.forEach((row) => {
                            const option = document.createElement('option');
                            option.value = row.id;
                            option.textContent = row.label;
                            employeeSelect.appendChild(option);
                        });

                        const hasMatch = rows.some((row) => String(row.id) === String(currentSelection));
                        setValue(hasMatch ? currentSelection : '');

                        if (feedback) {
                            feedback.textContent = rows.length === 0 ? 'No employees available for this department.' : '';
                        }
                    };

                    const fetchEmployees = async () => {
                        const department = departmentSelect.value ? departmentSelect.value : 'all';
                        const url = endpointTemplate.replace('__DEPARTMENT__', encodeURIComponent(department));

                        employeeSelect.disabled = true;
                        if (feedback) {
                            feedback.textContent = 'Loading employees...';
                        }

                        try {
                            const response = await fetch(url, { headers: { 'Accept': 'application/json' } });
                            const payload = await response.json();
                            populateEmployees(payload.data || []);
                        } catch (error) {
                            console.error('Unable to fetch employees', error);
                            employeeSelect.innerHTML = '<option value="">All Employees</option>';
                            setValue('');
                            if (feedback) {
                                feedback.textContent = 'Failed to load employees.';
                            }
                        } finally {
                            employeeSelect.disabled = false;
                        }
                    };

                    departmentSelect.addEventListener('change', () => {
                        container.dataset.selectedEmployee = '';
                        fetchEmployees();
                    });

                    employeeSelect.addEventListener('change', () => {
                        container.dataset.selectedEmployee = employeeSelect.value;
                        setValue(employeeSelect.value);
                    });

                    container.dataset.dependentInitialized = 'true';
                    fetchEmployees();
                });
            };

            const boot = () => initDependentDropdowns();

            document.addEventListener('DOMContentLoaded', boot);
            document.addEventListener('livewire:init', () => {
                boot();
                if (window.Livewire?.hook) {
                    Livewire.hook('message.processed', () => {
                        requestAnimationFrame(() => initDependentDropdowns());
                    });
                }
            });
        })();
    </script>
@endonce
