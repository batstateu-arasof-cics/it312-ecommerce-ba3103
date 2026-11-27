@extends('layouts.app')

@section('title', $order->number())

@section('content')
<div class="bg-white border border-gray-200 rounded-xl shadow-sm p-5 mb-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <a href="{{ route('orders.index') }}" class="text-sm text-gray-500 hover:text-gray-900 hover:underline">&larr; Back to my orders</a>
            <div class="flex flex-wrap items-center gap-3 mt-1.5">
                <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">{{ $order->number() }}</h1>
                @include('orders::partials.status-badge', ['status' => $order->status, 'size' => 'md'])
            </div>
            <p class="text-sm text-gray-500 mt-1">Placed on {{ $order->created_at->format('M d, Y g:i A') }}</p>
        </div>

        <div class="flex flex-col items-start sm:items-end gap-1.5">
            @if ($order->isCancellable())
                <form method="POST" action="{{ route('orders.cancel', $order) }}" onsubmit="return confirm('Cancel this order? This cannot be undone.');">
                    @csrf
                    <button type="submit" class="px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium hover:bg-rose-700 transition cursor-pointer">
                        Cancel order
                    </button>
                </form>
                <p class="text-xs text-gray-500">Pending orders can be cancelled before payment or shipment.</p>
            @endif
        </div>
    </div>
</div>

@include('orders::partials.detail-sections', ['order' => $order, 'payment' => $payment, 'shipment' => $shipment])
@endsection
