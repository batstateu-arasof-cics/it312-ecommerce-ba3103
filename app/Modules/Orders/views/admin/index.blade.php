@extends('layouts.app')

@section('title', 'Admin · Orders')

@section('content')
@php
    $hasActiveFilters = $filters['status'] !== '' || $filters['date_from'] !== '' || $filters['date_to'] !== '' || $filters['search'] !== '';
@endphp

<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
        <a href="{{ route('admin.dashboard') }}" class="text-sm text-gray-500 hover:text-gray-900 hover:underline">&larr; Back to dashboard</a>
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 mt-1">Manage orders</h1>
        <p class="text-sm text-gray-500 mt-0.5">Filter customer orders, inspect details, and update fulfillment statuses.</p>
    </div>
</div>

{{-- Quick status summary --}}
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">
    <a href="{{ route('admin.orders.index') }}" class="rounded-xl border p-3.5 bg-white transition {{ $filters['status'] === '' ? 'border-gray-900 ring-1 ring-gray-900' : 'border-gray-200 hover:border-gray-300' }}">
        <p class="text-xs text-gray-500">All orders</p>
        <p class="text-xl font-bold text-gray-900 mt-1">{{ $totalOrdersCount }}</p>
    </a>
    @foreach (\App\Modules\Orders\Models\Order::STATUSES as $status)
        <a href="{{ route('admin.orders.index', array_merge(request()->except('page'), ['status' => $status])) }}" class="rounded-xl border p-3.5 bg-white transition {{ $filters['status'] === $status ? 'border-gray-900 ring-1 ring-gray-900' : 'border-gray-200 hover:border-gray-300' }}">
            <p class="text-xs text-gray-500 capitalize">{{ $status }}</p>
            <p class="text-xl font-bold text-gray-900 mt-1">{{ (int) ($statusCounts[$status] ?? 0) }}</p>
        </a>
    @endforeach
</div>

{{-- Filter bar --}}
<form method="GET" action="{{ route('admin.orders.index') }}" class="bg-white border border-gray-200 rounded-xl shadow-sm p-4 mb-6">
    @if ($filterError)
        <div class="mb-3 rounded-lg bg-rose-50 border border-rose-200 px-3 py-2 text-xs text-rose-700 font-medium">
            {{ $filterError }}
        </div>
    @endif

    <div class="grid sm:grid-cols-2 lg:grid-cols-5 gap-3 items-end">
        <div class="lg:col-span-2">
            <label for="search" class="block text-xs font-medium text-gray-600 mb-1">Order # or customer</label>
            <input
                id="search"
                type="text"
                name="search"
                value="{{ $filters['search'] }}"
                placeholder="ORD-000012, name, or email"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-gray-900 focus:outline-none"
            >
        </div>

        <div>
            <label for="status" class="block text-xs font-medium text-gray-600 mb-1">Status</label>
            <select id="status" name="status" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm capitalize focus:border-gray-900 focus:outline-none">
                <option value="">All statuses</option>
                @foreach (\App\Modules\Orders\Models\Order::STATUSES as $status)
                    <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ $status }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="date_from" class="block text-xs font-medium text-gray-600 mb-1">From date</label>
            <input
                id="date_from"
                type="date"
                name="date_from"
                value="{{ $filters['date_from'] }}"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-gray-900 focus:outline-none"
            >
        </div>

        <div>
            <label for="date_to" class="block text-xs font-medium text-gray-600 mb-1">To date</label>
            <input
                id="date_to"
                type="date"
                name="date_to"
                value="{{ $filters['date_to'] }}"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-gray-900 focus:outline-none"
            >
        </div>
    </div>

    <div class="mt-3 flex flex-wrap items-center justify-end gap-2">
        @if ($hasActiveFilters)
            <a href="{{ route('admin.orders.index') }}" class="rounded-lg border border-gray-200 px-3.5 py-2 text-xs font-medium text-gray-600 hover:bg-gray-50">
                Reset filters
            </a>
        @endif
        <button type="submit" class="rounded-lg bg-gray-900 px-4 py-2 text-xs font-semibold text-white hover:bg-gray-800 transition cursor-pointer">
            Apply filters
        </button>
    </div>
</form>

@if ($orders->isEmpty())
    <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-10 text-center">
        @if ($hasActiveFilters)
            <h2 class="text-base font-semibold text-gray-900">No orders match your filters</h2>
            <p class="text-sm text-gray-500 mt-1">Try adjusting your search keyword, status, or date range.</p>
            <a href="{{ route('admin.orders.index') }}" class="inline-flex items-center mt-4 rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800">
                Clear filters
            </a>
        @else
            <h2 class="text-base font-semibold text-gray-900">No orders placed yet</h2>
            <p class="text-sm text-gray-500 mt-1">Customer orders will appear here as soon as checkouts are completed.</p>
        @endif
    </div>
@else
    {{-- Mobile cards --}}
    <div class="space-y-3 lg:hidden">
        @foreach ($orders as $order)
            @php
                $nextStatuses = $order->nextStatuses();
            @endphp
            <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-4 space-y-3">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <a href="{{ route('admin.orders.show', $order) }}" class="font-semibold text-blue-600 hover:underline">{{ $order->number() }}</a>
                        <p class="text-xs text-gray-500 mt-0.5">{{ $order->created_at->format('M d, Y g:i A') }}</p>
                    </div>
                    @include('orders::partials.status-badge', ['status' => $order->status])
                </div>

                <div class="text-sm">
                    <p class="font-medium text-gray-900">{{ $order->user?->name ?? 'Unknown' }}</p>
                    <p class="text-xs text-gray-500">{{ $order->user?->email }}</p>
                </div>

                <div class="flex items-center justify-between text-sm border-t border-gray-100 pt-2.5">
                    <span class="text-xs uppercase font-medium text-gray-600">{{ $order->payment_method }} &middot; {{ $order->items_count }} {{ $order->items_count === 1 ? 'item' : 'items' }}</span>
                    <span class="font-bold text-gray-900">₱{{ number_format((float) $order->total, 2) }}</span>
                </div>

                @if (count($nextStatuses) > 0)
                    <form method="POST" action="{{ route('admin.orders.status', $order) }}" class="flex items-center gap-2 pt-1">
                        @csrf
                        @method('PATCH')
                        <select name="status" required class="flex-1 border border-gray-300 rounded-lg px-2.5 py-1.5 text-sm capitalize bg-white">
                            <option value="" selected disabled>Move to&hellip;</option>
                            @foreach ($nextStatuses as $status)
                                <option value="{{ $status }}">{{ $status }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="px-3.5 py-1.5 rounded-lg bg-gray-900 text-white text-xs font-medium hover:bg-gray-800 cursor-pointer">
                            Update
                        </button>
                    </form>
                @endif
            </div>
        @endforeach
    </div>

    {{-- Desktop table --}}
    <div class="hidden lg:block bg-white border border-gray-200 rounded-xl shadow-sm overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="text-left border-b border-gray-200 bg-gray-50/70 text-xs uppercase tracking-wider text-gray-500">
                <tr>
                    <th class="px-4 py-3.5 font-medium">Order</th>
                    <th class="px-4 py-3.5 font-medium">Customer</th>
                    <th class="px-4 py-3.5 font-medium">Date</th>
                    <th class="px-4 py-3.5 font-medium">Payment</th>
                    <th class="px-4 py-3.5 text-right font-medium">Total</th>
                    <th class="px-4 py-3.5 font-medium">Status</th>
                    <th class="px-4 py-3.5 font-medium">Update</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($orders as $order)
                    @php
                        $nextStatuses = $order->nextStatuses();
                    @endphp
                    <tr class="hover:bg-gray-50/60 transition">
                        <td class="px-4 py-3.5 font-semibold whitespace-nowrap">
                            <a href="{{ route('admin.orders.show', $order) }}" class="text-blue-600 hover:underline">
                                {{ $order->number() }}
                            </a>
                            <p class="text-xs font-normal text-gray-400">{{ $order->items_count }} {{ $order->items_count === 1 ? 'item' : 'items' }}</p>
                        </td>
                        <td class="px-4 py-3.5">
                            <div class="font-medium text-gray-900">{{ $order->user?->name ?? 'Unknown' }}</div>
                            <div class="text-xs text-gray-500">{{ $order->user?->email }}</div>
                        </td>
                        <td class="px-4 py-3.5 text-gray-600 whitespace-nowrap">{{ $order->created_at->format('M d, Y') }}</td>
                        <td class="px-4 py-3.5 whitespace-nowrap">
                            <span class="uppercase text-xs font-medium text-gray-700">{{ $order->payment_method }}</span>
                            @if ($order->payment)
                                <span class="block text-xs text-gray-400 capitalize">{{ $order->payment->status }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3.5 text-right font-semibold text-gray-900 whitespace-nowrap">₱{{ number_format((float) $order->total, 2) }}</td>
                        <td class="px-4 py-3.5 whitespace-nowrap">
                            @include('orders::partials.status-badge', ['status' => $order->status])
                        </td>
                        <td class="px-4 py-3.5 whitespace-nowrap">
                            @if (count($nextStatuses) > 0)
                                <form method="POST" action="{{ route('admin.orders.status', $order) }}" class="flex items-center gap-2">
                                    @csrf
                                    @method('PATCH')
                                    <select name="status" required class="border border-gray-300 rounded-lg px-2.5 py-1 text-xs capitalize bg-white focus:border-gray-900 focus:outline-none">
                                        <option value="" selected disabled>Move to&hellip;</option>
                                        @foreach ($nextStatuses as $status)
                                            <option value="{{ $status }}">{{ $status }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="px-3 py-1 rounded-lg bg-gray-900 text-white text-xs font-medium hover:bg-gray-800 transition cursor-pointer">
                                        Update
                                    </button>
                                </form>
                            @else
                                <span class="text-xs text-gray-400">Finalized</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-6">{{ $orders->links() }}</div>
@endif
@endsection
