<div class="p-6 space-y-4">
    <h1 class="text-xl font-semibold">Attendance Schedule</h1>

    @if (session('status'))
        <div class="p-3 rounded bg-green-50 text-green-700 border border-green-200">{{ session('status') }}</div>
    @endif

    <div class="bg-white dark:bg-neutral-900 rounded-lg shadow p-4">
        <form wire:submit.prevent="save" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm mb-1">Time-in Window</label>
                    <div class="flex items-center gap-2">
                        <input type="time" wire:model="in_start" class="border rounded px-2 py-2 dark:bg-neutral-800 dark:border-neutral-700">
                        <span>to</span>
                        <input type="time" wire:model="in_end" class="border rounded px-2 py-2 dark:bg-neutral-800 dark:border-neutral-700">
                    </div>
                    @error('in_start') <div class="text-red-600 text-xs">{{ $message }}</div> @enderror
                    @error('in_end') <div class="text-red-600 text-xs">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label class="block text-sm mb-1">Time-out Window</label>
                    <div class="flex items-center gap-2">
                        <input type="time" wire:model="out_start" class="border rounded px-2 py-2 dark:bg-neutral-800 dark:border-neutral-700">
                        <span>to</span>
                        <input type="time" wire:model="out_end" class="border rounded px-2 py-2 dark:bg-neutral-800 dark:border-neutral-700">
                    </div>
                    @error('out_start') <div class="text-red-600 text-xs">{{ $message }}</div> @enderror
                    @error('out_end') <div class="text-red-600 text-xs">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm mb-1">Late After (optional)</label>
                    <input type="time" wire:model="late_after" class="border rounded px-2 py-2 w-full dark:bg-neutral-800 dark:border-neutral-700">
                    <div class="text-xs text-gray-500">If set, time-in after this time is marked late; otherwise the end of the Time-in Window is used.</div>
                    @error('late_after') <div class="text-red-600 text-xs">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label class="block text-sm mb-1">Late Grace (minutes)</label>
                    <input type="number" min="0" wire:model="late_grace" class="border rounded px-2 py-2 w-full dark:bg-neutral-800 dark:border-neutral-700">
                    @error('late_grace') <div class="text-red-600 text-xs">{{ $message }}</div> @enderror
                </div>
            </div>

            <div>
                <label class="block text-sm mb-1">Days Enabled</label>
                <div class="grid grid-cols-2 md:grid-cols-7 gap-2">
                    @php $daysMap = [1=>'Mon',2=>'Tue',3=>'Wed',4=>'Thu',5=>'Fri',6=>'Sat',7=>'Sun']; @endphp
                    @foreach($daysMap as $num=>$lbl)
                        <label class="inline-flex items-center gap-2 border rounded px-2 py-1 dark:bg-neutral-800 dark:border-neutral-700">
                            <input type="checkbox" wire:model="days" value="{{ $num }}">
                            <span>{{ $lbl }}</span>
                        </label>
                    @endforeach
                </div>
                @error('days') <div class="text-red-600 text-xs">{{ $message }}</div> @enderror
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm mb-1">Effective From (optional)</label>
                    <input type="date" wire:model="from_date" class="border rounded px-2 py-2 w-full dark:bg-neutral-800 dark:border-neutral-700">
                    @error('from_date') <div class="text-red-600 text-xs">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label class="block text-sm mb-1">Effective To (optional)</label>
                    <input type="date" wire:model="to_date" class="border rounded px-2 py-2 w-full dark:bg-neutral-800 dark:border-neutral-700">
                    @error('to_date') <div class="text-red-600 text-xs">{{ $message }}</div> @enderror
                </div>
            </div>

            <div>
                <button class="inline-flex items-center px-3 py-2 rounded bg-green-600 text-white hover:bg-green-700">Save Schedule</button>
            </div>
        </form>
    </div>
</div>
