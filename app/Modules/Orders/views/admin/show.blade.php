@extends('layouts.app')

@section('title', 'Admin · '.$order->number())

@section('content')
@php
    $nextStatuses = $order->nextStatuses();
@endphp

<div class="bg-white border border-gray-200 rounded-xl shadow-sm p-5 mb-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <a href="{{ route('admin.orders.index') }}" class="text-sm text-gray-500 hover:text-gray-900 hover:underline">&larr; Back to all orders</a>
            <div class="flex flex-wrap items-center gap-3 mt-1.5">
                <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">{{ $order->number() }}</h1>
                @include('orders::partials.status-badge', ['status' => $order->status, 'size' => 'md'])
            </div>
            <p class="text-sm text-gray-500 mt-1">
                Placed on {{ $order->created_at->format('M d, Y g:i A') }}
                by <span class="font-medium text-gray-800">{{ $order->user?->name ?? 'Unknown' }}</span>
                @if ($order->user?->email)
                    ({{ $order->user->email }})
                @endif
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            @if (count($nextStatuses) > 0)
                <form method="POST" action="{{ route('admin.orders.status', $order) }}" class="flex items-center gap-2">
                    @csrf
                    @method('PATCH')
                    <select name="status" required class="border border-gray-300 rounded-lg px-3 py-2 text-sm capitalize bg-white focus:border-gray-900 focus:outline-none">
                        <option value="" selected disabled>Move to&hellip;</option>
                        @foreach ($nextStatuses as $status)
                            <option value="{{ $status }}">{{ $status }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="px-4 py-2 rounded-lg bg-gray-900 text-white text-sm font-medium hover:bg-gray-800 transition cursor-pointer">
                        Update status
                    </button>
                </form>
            @else
                <span class="text-xs font-medium text-gray-500 bg-gray-100 rounded-lg px-3 py-1.5">No further status transitions</span>
            @endif
        </div>
    </div>
</div>

@include('orders::partials.detail-sections', ['order' => $order, 'payment' => $payment, 'shipment' => $shipment])
@endsection
