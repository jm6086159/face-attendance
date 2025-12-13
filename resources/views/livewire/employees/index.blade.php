<div class="p-6 space-y-4">
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold">Employees</h1>
        <a href="{{ route('employees.create') }}" class="inline-flex items-center px-3 py-2 rounded-md bg-blue-600 text-white hover:bg-blue-700 transition">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12m6-6H6"/></svg>
            New
        </a>
    </div>

    <div class="bg-white dark:bg-neutral-900 rounded-lg shadow p-4">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
            <div class="flex flex-wrap items-center gap-2">
                <input type="text" wire:model.debounce.300ms="q" placeholder="Search code/name/department" class="border rounded px-3 py-2 w-64 dark:bg-neutral-800 dark:border-neutral-700">
                <div class="flex flex-wrap items-center gap-2"
                data-dependent-dropdown
                data-endpoint="{{ route('departments.employees', ['department' => '__DEPARTMENT__']) }}"
                data-selected-employee="{{ $selectedEmployee ?? '' }}">
                <select data-role="department-select" wire:model="department" class="border rounded px-2 py-2 dark:bg-neutral-800 dark:border-neutral-700">
                    <option value="">All Departments</option>
                    @foreach ($departments as $dept)
                        <option value="{{ $dept }}">{{ $dept }}</option>
                    @endforeach
                </select>
                <div class="flex flex-col">
                    <select data-role="employee-select" class="border rounded px-2 py-2 dark:bg-neutral-800 dark:border-neutral-700">
                        <option value="">All Employees</option>
                        @foreach ($employeeOptions as $option)
                            <option value="{{ $option->id }}">
                                {{ $option->emp_code ?? 'N/A' }} ({{ trim($option->first_name . ' ' . $option->last_name) }})
                            </option>
                        @endforeach
                    </select>
                    <input type="hidden" data-role="employee-value" wire:model.live="selectedEmployee" value="{{ $selectedEmployee ?? '' }}">
                    <p class="text-xs text-gray-500 dark:text-gray-400 pt-1" data-role="feedback"></p>
                </div>
            </div>
            </div>
            <select wire:model="perPage" class="border rounded px-2 py-2 dark:bg-neutral-800 dark:border-neutral-700">
                <option value="5">5</option>
                <option value="10">10</option>
                <option value="25">25</option>
            </select>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="sticky top-0 z-10">
                    <tr class="bg-gray-100 dark:bg-neutral-800 text-left">
                        <th class="p-2">ID</th>
                        <th class="p-2">Code</th>
                        <th class="p-2">Name</th>
                        <th class="p-2">Department</th>
                        <th class="p-2">Position</th>
                        <th class="p-2">Active</th>
                        <th class="p-2">Faces</th>
                        <th class="p-2">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($employees as $emp)
                        <tr class="border-b hover:bg-gray-50 border-gray-100">
                            <td class="p-2">{{ $emp->id }}</td>
                            <td class="p-2 font-medium">{{ $emp->emp_code }}</td>
                            <td class="p-2">{{ $emp->full_name }}</td>
                            @php
                                $deptDisplay = $emp->department ?? '';
                                if ($emp->course && strpos($deptDisplay, '/') === false) {
                                    $deptDisplay = trim($deptDisplay . '/' . $emp->course);
                                }
                            @endphp
                            <td class="p-2">{{ $deptDisplay !== '' ? $deptDisplay : 'â€”' }}</td>
                            <td class="p-2">{{ $emp->position ?? 'â€”' }}</td>
                            <td class="p-2">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $emp->active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $emp->active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="p-2">
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium {{ $emp->templates_count > 0 ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                    {{ $emp->templates_count }}
                                </span>
                            </td>
                            <td class="p-2">
                                <div class="flex gap-3">
                                    <a title="Edit" href="{{ route('employees.edit', $emp->id) }}" class="text-blue-600 hover:underline">Edit</a>
                                    <a title="Register Face" href="{{ route('face.registration', $emp->id) }}" target="_blank" class="text-green-600 hover:underline">Face</a>
                                    <button title="Delete" class="text-red-600 hover:underline"
                                        wire:click="delete({{ $emp->id }})"
                                        onclick="return confirm('Delete {{ $emp->full_name }} ({{ $emp->emp_code }})? This removes face templates too.');">
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="p-6 text-center text-gray-500">No employees found.</td></tr>
                    @endforelse


                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $employees->links() }}</div>
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
