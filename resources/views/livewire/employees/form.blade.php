
<div class="p-6 max-w-2xl">
    <h1 class="text-xl font-semibold mb-4">
        {{ $employee ? 'Edit Employee' : 'Register New Employee' }}
    </h1>

    @if ($errors->any())
        <div class="mb-3 text-sm text-red-700 bg-red-100 border border-red-200 rounded p-2">
            <ul class="list-disc list-inside">
                @foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach
            </ul>
        </div>
    @endif

    <form wire:submit.prevent="save" class="space-y-4">
        <div>
            <label class="block text-sm mb-1">Employee Code *</label>
            <input type="text" wire:model.defer="emp_code" class="border rounded w-full px-3 py-2">
            @error('emp_code') <div class="text-xs text-red-600">{{ $message }}</div> @enderror
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-sm mb-1">First Name *</label>
                <input type="text" wire:model.defer="first_name" class="border rounded w-full px-3 py-2">
                @error('first_name') <div class="text-xs text-red-600">{{ $message }}</div> @enderror
            </div>
            <div>
                <label class="block text-sm mb-1">Last Name *</label>
                <input type="text" wire:model.defer="last_name" class="border rounded w-full px-3 py-2">
                @error('last_name') <div class="text-xs text-red-600">{{ $message }}</div> @enderror
            </div>
        </div>

        <div>
            <label class="block text-sm mb-1">Email</label>
            <input type="email" wire:model.defer="email" class="border rounded w-full px-3 py-2">
            @error('email') <div class="text-xs text-red-600">{{ $message }}</div> @enderror
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-sm mb-1">Department</label>
                <select wire:model.live="department" class="border rounded w-full px-3 py-2 bg-white dark:bg-zinc-800">
                    <option value="">Select Department</option>
                    @foreach($this->departmentOptions as $opt)
                        <option value="{{ $opt }}">{{ $opt }}</option>
                    @endforeach
                </select>
                @error('department') <div class="text-xs text-red-600">{{ $message }}</div> @enderror
            </div>
            <div>
                <label class="block text-sm mb-1">Position</label>
                <select wire:model.live="position" class="border rounded w-full px-3 py-2 bg-white dark:bg-zinc-800" {{ empty($this->positionOptions) ? 'disabled' : '' }}>
                    <option value="">{{ $department ? 'Select Position' : 'Select Department first' }}</option>
                    @foreach($this->positionOptions as $opt)
                        <option value="{{ $opt }}">{{ $opt }}</option>
                    @endforeach
                </select>
                @error('position') <div class="text-xs text-red-600">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-sm mb-1">Password {{ $employee ? '(leave blank to keep current)' : '*' }}</label>
                <input type="password" wire:model.defer="password" class="border rounded w-full px-3 py-2" autocomplete="new-password">
                @error('password') <div class="text-xs text-red-600">{{ $message }}</div> @enderror
            </div>
            <div>
                <label class="block text-sm mb-1">Confirm Password {{ $employee ? '' : '*' }}</label>
                <input type="password" wire:model.defer="password_confirmation" class="border rounded w-full px-3 py-2" autocomplete="new-password">
            </div>
        </div>

        <div class="flex items-center gap-2 pt-1">
            <input id="active" type="checkbox" wire:model.defer="active" class="border rounded">
            <label for="active" class="text-sm">Active</label>
        </div>

        {{-- <div>
            <label class="block text-sm mb-1">Face Photos (upload 1–3 clear images)</label>
            <input type="file" wire:model="photos" multiple class="block w-full text-sm">
            @error('photos.*') <div class="text-xs text-red-600">{{ $message }}</div> @enderror
        </div> --}}

        <div class="pt-2">
            <button class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Save</button>
            <a href="{{ route('employees.index') }}" class="ml-3 text-gray-700 hover:underline">Cancel</a>
            @if($employee)
                <a href="{{ route('face.registration', $employee->id) }}" target="_blank" class="ml-3 text-blue-500 underline">Capture Face</a>
            @endif
        </div>

        @if(session('employee_created'))
            <div class="mt-4 p-4 bg-green-50 border border-green-200 rounded-lg">
                <h3 class="text-lg font-semibold text-green-800 mb-2">Employee Created Successfully!</h3>
                <p class="text-green-700 mb-3">Employee {{ session('employee_created')->full_name }} ({{ session('employee_created')->emp_code }}) has been registered.</p>
                <div class="flex gap-3">
                    <a href="{{ route('face.registration', session('employee_created')->id) }}" target="_blank" 
                       class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 inline-flex items-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        Register Face Now
                    </a>
                    <a href="{{ route('employees.index') }}" 
                       class="px-4 py-2 bg-gray-600 text-white rounded hover:bg-gray-700">
                        Back to Employees
                    </a>
                </div>
            </div>
        @endif
    </form>
</div>
