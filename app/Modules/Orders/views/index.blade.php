@extends('layouts.app')

@section('title', 'My orders')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">My orders</h1>
        <p class="text-sm text-gray-500 mt-1">Track your purchases, view payment status, and manage pending orders.</p>
    </div>

    <a href="{{ route('catalog.index') }}" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
        Continue shopping
    </a>
</div>

{{-- Status filter tabs --}}
@if ($totalOrdersCount > 0)
    <div class="flex flex-wrap items-center gap-2 mb-6">
        <a
            href="{{ route('orders.index') }}"
            class="inline-flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-xs font-medium transition {{ $activeStatus === null ? 'bg-gray-900 text-white' : 'bg-white border border-gray-200 text-gray-700 hover:bg-gray-50' }}"
        >
            <span>All</span>
            <span class="rounded-full px-1.5 py-0.5 text-[11px] {{ $activeStatus === null ? 'bg-white/20 text-white' : 'bg-gray-100 text-gray-600' }}">{{ $totalOrdersCount }}</span>
        </a>

        @foreach (\App\Modules\Orders\Models\Order::STATUSES as $status)
            @php
                $count = (int) ($statusCounts[$status] ?? 0);
                $isActive = $activeStatus === $status;
            @endphp
            <a
                href="{{ route('orders.index', ['status' => $status]) }}"
                class="inline-flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-xs font-medium capitalize transition {{ $isActive ? 'bg-gray-900 text-white' : 'bg-white border border-gray-200 text-gray-700 hover:bg-gray-50' }}"
            >
                <span>{{ $status }}</span>
                <span class="rounded-full px-1.5 py-0.5 text-[11px] {{ $isActive ? 'bg-white/20 text-white' : 'bg-gray-100 text-gray-600' }}">{{ $count }}</span>
            </a>
        @endforeach
    </div>
@endif

@if ($orders->isEmpty())
    <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-10 text-center">
        <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 text-gray-400">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
            </svg>
        </div>

        @if ($activeStatus)
            <h2 class="text-base font-semibold text-gray-900">No {{ $activeStatus }} orders found</h2>
            <p class="text-sm text-gray-500 mt-1">You currently do not have any orders with status "{{ $activeStatus }}".</p>
            <a href="{{ route('orders.index') }}" class="inline-flex items-center mt-4 rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800">
                View all orders
            </a>
        @else
            <h2 class="text-base font-semibold text-gray-900">No orders yet</h2>
            <p class="text-sm text-gray-500 mt-1">When you place an order at checkout, it will appear here.</p>
            <a href="{{ route('catalog.index') }}" class="inline-flex items-center mt-4 rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800">
                Browse products
            </a>
        @endif
    </div>
@else
    {{-- Mobile cards --}}
    <div class="space-y-3 md:hidden">
        @foreach ($orders as $order)
            <a href="{{ route('orders.show', $order) }}" class="block bg-white border border-gray-200 rounded-xl shadow-sm p-4 hover:border-gray-300 transition">
                <div class="flex items-center justify-between gap-3">
                    <span class="font-semibold text-gray-900">{{ $order->number() }}</span>
                    @include('orders::partials.status-badge', ['status' => $order->status])
                </div>
                <div class="mt-2 flex items-center justify-between text-xs text-gray-500">
                    <span>{{ $order->created_at->format('M d, Y') }} &middot; {{ $order->items_count }} {{ $order->items_count === 1 ? 'item' : 'items' }}</span>
                    <span class="uppercase font-medium text-gray-700">{{ $order->payment_method }}</span>
                </div>
                <div class="mt-3 pt-3 border-t border-gray-100 flex items-center justify-between">
                    <span class="text-xs text-gray-500">Total</span>
                    <span class="text-sm font-bold text-gray-900">₱{{ number_format((float) $order->total, 2) }}</span>
                </div>
            </a>
        @endforeach
    </div>

    {{-- Desktop table --}}
    <div class="hidden md:block bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead class="text-left border-b border-gray-200 bg-gray-50/70 text-xs uppercase tracking-wider text-gray-500">
                <tr>
                    <th class="px-5 py-3.5 font-medium">Order</th>
                    <th class="px-5 py-3.5 font-medium">Date</th>
                    <th class="px-5 py-3.5 font-medium">Items</th>
                    <th class="px-5 py-3.5 font-medium">Status</th>
                    <th class="px-5 py-3.5 font-medium">Payment</th>
                    <th class="px-5 py-3.5 text-right font-medium">Total</th>
                    <th class="px-5 py-3.5 text-right font-medium">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($orders as $order)
                    <tr class="hover:bg-gray-50/60 transition">
                        <td class="px-5 py-3.5 font-semibold">
                            <a href="{{ route('orders.show', $order) }}" class="text-blue-600 hover:underline">{{ $order->number() }}</a>
                        </td>
                        <td class="px-5 py-3.5 text-gray-600">{{ $order->created_at->format('M d, Y') }}</td>
                        <td class="px-5 py-3.5 text-gray-600">{{ $order->items_count }} {{ $order->items_count === 1 ? 'item' : 'items' }}</td>
                        <td class="px-5 py-3.5">
                            @include('orders::partials.status-badge', ['status' => $order->status])
                        </td>
                        <td class="px-5 py-3.5">
                            <span class="uppercase text-xs font-medium text-gray-700">{{ $order->payment_method }}</span>
                            @if ($order->payment)
                                <span class="text-xs text-gray-400 capitalize">&middot; {{ $order->payment->status }}</span>
                            @endif
                        </td>
                        <td class="px-5 py-3.5 text-right font-semibold text-gray-900">₱{{ number_format((float) $order->total, 2) }}</td>
                        <td class="px-5 py-3.5 text-right">
                            <a href="{{ route('orders.show', $order) }}" class="text-xs font-medium text-gray-700 hover:text-gray-900 underline">View details</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-6">{{ $orders->links() }}</div>
@endif
@endsection
