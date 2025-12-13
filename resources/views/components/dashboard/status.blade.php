@props(['title', 'status'])

<div class="p-4 bg-white rounded-2xl shadow border border-[rgba(0,0,0,0.06)]">
    <p class="text-sm text-gray-600 mb-1">{{ $title }}</p>
    <div class="flex items-center">
        @php($ok = Str::contains(strtolower($status), 'online') || Str::contains(strtolower($status), 'connected'))
        @if($ok)
            <flux:icon name="check-circle" class="text-[var(--brand-green,#0f8b48)] w-5 h-5 mr-2" />
        @else
            <flux:icon name="x-circle" class="text-red-500 w-5 h-5 mr-2" />
        @endif
        <span class="font-semibold text-gray-900">{{ $status }}</span>
    </div>
</div>
