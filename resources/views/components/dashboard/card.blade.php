@props(['title', 'value' => 0, 'icon' => 'circle', 'color' => 'gray'])

<div class="flex items-center p-4 bg-white rounded-2xl shadow border border-[rgba(0,0,0,0.06)]">
    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-[var(--brand-green,#0f8b48)]/10 border border-[rgba(15,139,72,0.25)]">
        <flux:icon :name="$icon" class="w-5 h-5 text-[var(--brand-green,#0f8b48)]" />
    </div>
    <div class="ml-3">
        <p class="text-sm font-medium text-gray-600">{{ $title }}</p>
        <h3 class="text-2xl font-bold text-gray-900">{{ $value }}</h3>
    </div>
</div>
