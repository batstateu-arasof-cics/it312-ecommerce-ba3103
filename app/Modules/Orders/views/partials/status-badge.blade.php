@props([
    'status',
    'size' => 'sm',
])

@php
    $normalized = strtolower((string) $status);
    $styles = match ($normalized) {
        'pending' => 'bg-amber-50 text-amber-800 ring-amber-200',
        'paid' => 'bg-blue-50 text-blue-800 ring-blue-200',
        'shipped', 'in_transit' => 'bg-indigo-50 text-indigo-800 ring-indigo-200',
        'completed', 'delivered' => 'bg-emerald-50 text-emerald-800 ring-emerald-200',
        'cancelled', 'failed' => 'bg-rose-50 text-rose-800 ring-rose-200',
        default => 'bg-gray-100 text-gray-700 ring-gray-200',
    };

    $dotStyles = match ($normalized) {
        'pending' => 'bg-amber-500',
        'paid' => 'bg-blue-500',
        'shipped', 'in_transit' => 'bg-indigo-500',
        'completed', 'delivered' => 'bg-emerald-500',
        'cancelled', 'failed' => 'bg-rose-500',
        default => 'bg-gray-400',
    };

    $padding = $size === 'md' ? 'px-3 py-1 text-xs' : 'px-2.5 py-0.5 text-xs';
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1.5 rounded-full font-medium capitalize ring-1 ring-inset {$padding} {$styles}"]) }}>
    <span class="h-1.5 w-1.5 rounded-full {{ $dotStyles }}" aria-hidden="true"></span>
    <span>{{ str_replace('_', ' ', $normalized) }}</span>
</span>
