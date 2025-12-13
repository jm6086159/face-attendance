<x-layouts.app.sidebar title="Dashboard">
    <flux:main class="p-6 space-y-8">

        {{-- Hero header --}}
        <div class="rounded-xl p-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4 border" style="background:linear-gradient(90deg, rgba(15,139,72,0.15) 0%, rgba(232,180,0,0.18) 100%); border-color: rgba(15,139,72,0.3)">
            <div>
                <h1 class="text-2xl font-semibold">Smart Face Attendance</h1>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Capture faces, recognize employees, and log attendance automatically.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('face.attendance') }}" target="_blank" class="inline-flex items-center px-3 py-2 rounded-lg text-white transition" style="background:#1b7f3a">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    Start Attendance
                </a>
            </div>
        </div>

        {{-- Top cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <x-dashboard.card title="Total Employees" :value="$totalEmployees ?? 0" icon="users" color="blue" />
            <x-dashboard.card title="Present Today" :value="$present ?? 0" icon="check-circle" color="green" />
            <x-dashboard.card title="Late" :value="$late ?? 0" icon="clock" color="amber" />
            <x-dashboard.card title="Absent" :value="$absent ?? 0" icon="x-circle" color="red" />
        </div>

        {{-- Charts --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <livewire:dashboard.attendance-chart />
            <livewire:dashboard.department-chart />
        </div>

        {{-- Quick Actions removed intentionally --}}

        {{-- Recent logs --}}
        <div class="mt-6">
            <livewire:dashboard.recent-logs />
        </div>

        {{-- Optional status --}}
        <div class="mt-6 grid md:grid-cols-3 gap-4">
            <x-dashboard.status title="FastAPI Recognition" :status="$fastapiStatus ?? 'Online'" />
            <x-dashboard.status title="Database" :status="$dbStatus ?? 'Connected'" />
            <x-dashboard.status title="Cameras" :status="$cameraCount ?? '3/3 Active'" />
        </div>

    </flux:main>
</x-layouts.app.sidebar>
