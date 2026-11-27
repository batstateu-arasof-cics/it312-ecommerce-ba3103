@extends('layouts.app')

@section('title', 'Checkout')

@section('content')
@php
    $totalUnits = (int) $items->sum('quantity');
@endphp

<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <div>
        <a href="{{ route('cart.index') }}" class="text-sm text-gray-500 hover:text-gray-900 hover:underline">&larr; Back to cart</a>
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 mt-1">Checkout</h1>
        <p class="text-sm text-gray-500 mt-0.5">Review your shipping details, payment method, and order summary before placing your order.</p>
    </div>
</div>

@if ($unavailableItems->isNotEmpty())
    <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
        <p class="font-semibold">Some items in your cart have stock issues:</p>
        <ul class="mt-1 list-disc pl-5 space-y-0.5">
            @foreach ($unavailableItems as $unavailable)
                <li>{{ $unavailable->product?->name ?? 'Unavailable product' }} (requested {{ $unavailable->quantity }})</li>
            @endforeach
        </ul>
        <a href="{{ route('cart.index') }}" class="inline-block mt-2 font-medium underline hover:text-amber-950">Update your cart &rarr;</a>
    </div>
@endif

<form method="POST" action="{{ route('checkout.store') }}" class="grid lg:grid-cols-12 gap-6 lg:gap-8 items-start">
    @csrf
    <div class="lg:col-span-7 space-y-6">
        {{-- Shipping Address --}}
        <section class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-3.5 border-b border-gray-100 bg-gray-50/60 flex items-center justify-between">
                <h2 class="font-semibold text-gray-900">1. Shipping address</h2>
                <span class="text-xs text-gray-500">Delivery destination</span>
            </div>

            <div class="p-5">
                @if ($addresses->isNotEmpty())
                    <div class="space-y-3">
                        @foreach ($addresses as $savedAddress)
                            @php
                                $isSelected = $address && $address->id === $savedAddress->id;
                            @endphp
                            <label class="flex items-start gap-3 rounded-lg border p-3.5 cursor-pointer transition {{ $isSelected ? 'border-gray-900 bg-gray-50/70 ring-1 ring-gray-900' : 'border-gray-200 hover:border-gray-300' }}">
                                <input
                                    type="radio"
                                    name="address_id"
                                    value="{{ $savedAddress->id }}"
                                    class="mt-1 text-gray-900 focus:ring-gray-900"
                                    @checked($isSelected)
                                >
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="font-medium text-sm text-gray-900">{{ $savedAddress->recipient }}</span>
                                        <span class="text-xs text-gray-500">{{ $savedAddress->phone }}</span>
                                        @if ($savedAddress->is_default)
                                            <span class="rounded-full bg-gray-900 text-white px-2 py-0.5 text-[11px] font-medium">Default</span>
                                        @endif
                                    </div>
                                    <p class="text-sm text-gray-600 mt-1">{{ $savedAddress->fullAddress() }}</p>
                                </div>
                            </label>
                        @endforeach
                    </div>
                @elseif ($address)
                    <div class="rounded-lg border border-gray-200 bg-gray-50/60 p-3.5">
                        <p class="font-medium text-sm text-gray-900">{{ $address->recipient }} &middot; {{ $address->phone }}</p>
                        <p class="text-sm text-gray-600 mt-1">{{ $address->fullAddress() }}</p>
                    </div>
                @else
                    <div class="rounded-lg border border-dashed border-gray-300 bg-gray-50/70 p-4 text-sm text-gray-600">
                        <p class="font-medium text-gray-800">No saved shipping address yet</p>
                        <p class="text-xs text-gray-500 mt-1">
                            You can still place your order &mdash; the standard shipping rate (₱{{ number_format($shippingFee, 2) }}) will be applied.
                        </p>
                    </div>
                @endif

                @error('address_id')
                    <p class="mt-2 text-xs text-rose-600 font-medium">{{ $message }}</p>
                @enderror
            </div>
        </section>

        {{-- Payment Method --}}
        <section class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-3.5 border-b border-gray-100 bg-gray-50/60 flex items-center justify-between">
                <h2 class="font-semibold text-gray-900">2. Payment method</h2>
                <span class="text-xs text-gray-500">Choose how to pay</span>
            </div>

            <div class="p-5 grid sm:grid-cols-2 gap-3">
                <label class="flex items-start gap-3 rounded-lg border p-3.5 cursor-pointer transition {{ $selectedPaymentMethod === 'cod' ? 'border-gray-900 bg-gray-50/70 ring-1 ring-gray-900' : 'border-gray-200 hover:border-gray-300' }}">
                    <input
                        type="radio"
                        name="payment_method"
                        value="cod"
                        class="mt-1"
                        @checked($selectedPaymentMethod === 'cod')
                    >
                    <div>
                        <p class="font-medium text-sm text-gray-900">Cash on delivery</p>
                        <p class="text-xs text-gray-500 mt-0.5">Pay with cash when your parcel arrives.</p>
                    </div>
                </label>

                <label class="flex items-start gap-3 rounded-lg border p-3.5 cursor-pointer transition {{ $selectedPaymentMethod === 'online' ? 'border-gray-900 bg-gray-50/70 ring-1 ring-gray-900' : 'border-gray-200 hover:border-gray-300' }}">
                    <input
                        type="radio"
                        name="payment_method"
                        value="online"
                        class="mt-1"
                        @checked($selectedPaymentMethod === 'online')
                    >
                    <div>
                        <p class="font-medium text-sm text-gray-900">Pay online (GCash / card)</p>
                        <p class="text-xs text-gray-500 mt-0.5">Redirect to secure payment gateway.</p>
                    </div>
                </label>
            </div>

            @error('payment_method')
                <p class="px-5 pb-4 text-xs text-rose-600 font-medium">{{ $message }}</p>
            @enderror
        </section>

        {{-- Voucher --}}
        <section class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-3.5 border-b border-gray-100 bg-gray-50/60 flex items-center justify-between">
                <h2 class="font-semibold text-gray-900">3. Voucher code</h2>
                <span class="text-xs text-gray-500">Optional</span>
            </div>

            <div class="p-5">
                <div class="flex flex-col sm:flex-row gap-2.5">
                    <input
                        type="text"
                        name="voucher_code"
                        value="{{ old('voucher_code', $voucherCode) }}"
                        placeholder="Enter voucher code (e.g. WELCOME10)"
                        class="flex-1 rounded-lg border border-gray-300 px-3.5 py-2 text-sm uppercase placeholder:normal-case focus:border-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900"
                    >
                    <button
                        type="submit"
                        formaction="{{ route('checkout.show') }}"
                        formmethod="GET"
                        class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-800 hover:bg-gray-50 transition cursor-pointer"
                    >
                        Apply voucher
                    </button>
                    @if ($voucherCode && $discount > 0)
                        <a
                            href="{{ route('checkout.show', array_filter(['address_id' => $address?->id, 'payment_method' => $selectedPaymentMethod])) }}"
                            class="inline-flex items-center justify-center rounded-lg border border-gray-200 px-3 py-2 text-xs font-medium text-gray-600 hover:bg-gray-50"
                        >
                            Remove
                        </a>
                    @endif
                </div>

                @if ($discount > 0 && $voucherCode)
                    <div class="mt-3 flex items-center justify-between rounded-lg bg-emerald-50 border border-emerald-200 px-3.5 py-2 text-xs text-emerald-800">
                        <span>Voucher <strong class="font-mono">{{ $voucherCode }}</strong> applied.</span>
                        <span class="font-semibold">-₱{{ number_format($discount, 2) }}</span>
                    </div>
                @elseif ($voucherError)
                    <p class="mt-2.5 text-xs text-rose-600 font-medium">{{ $voucherError }}</p>
                @endif

                @error('voucher_code')
                    <p class="mt-2.5 text-xs text-rose-600 font-medium">{{ $message }}</p>
                @enderror
            </div>
        </section>
    </div>

    {{-- Order Summary --}}
    <aside class="lg:col-span-5 bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden lg:sticky lg:top-6">
        <div class="px-5 py-3.5 border-b border-gray-100 bg-gray-50/60 flex items-center justify-between">
            <h2 class="font-semibold text-gray-900">Order summary</h2>
            <span class="text-xs text-gray-500 font-medium">{{ $totalUnits }} {{ $totalUnits === 1 ? 'item' : 'items' }}</span>
        </div>

        <div class="p-5">
            <ul class="divide-y divide-gray-100 max-h-72 overflow-y-auto pr-1 mb-4">
                @foreach ($items as $item)
                    <li class="py-3 first:pt-0 last:pb-0 flex items-start justify-between gap-3">
                        <div class="flex items-start gap-3 min-w-0">
                            @if ($item->product)
                                <img src="{{ $item->product->imageUrl() }}" alt="{{ $item->product->name }}" class="w-11 h-11 rounded-lg object-cover border border-gray-200 shrink-0">
                            @endif
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-gray-900 truncate">{{ $item->product?->name ?? 'Unavailable product' }}</p>
                                <p class="text-xs text-gray-500 mt-0.5">
                                    ₱{{ number_format((float) ($item->product?->price ?? 0), 2) }} &times; {{ $item->quantity }}
                                </p>
                            </div>
                        </div>
                        <span class="text-sm font-semibold text-gray-900 shrink-0">₱{{ number_format($item->lineTotal(), 2) }}</span>
                    </li>
                @endforeach
            </ul>

            <div class="border-t border-gray-200 pt-4 text-sm space-y-2">
                <div class="flex justify-between text-gray-600">
                    <span>Subtotal</span>
                    <span class="text-gray-900 font-medium">₱{{ number_format($subtotal, 2) }}</span>
                </div>

                @if ($discount > 0)
                    <div class="flex justify-between text-emerald-700">
                        <span>Discount ({{ $voucherCode }})</span>
                        <span class="font-medium">-₱{{ number_format($discount, 2) }}</span>
                    </div>
                @endif

                <div class="flex justify-between text-gray-600">
                    <span>Shipping fee</span>
                    <span class="text-gray-900 font-medium">₱{{ number_format($shippingFee, 2) }}</span>
                </div>

                <div class="flex justify-between items-baseline font-bold text-base text-gray-900 border-t border-gray-200 pt-3 mt-2">
                    <span>Total</span>
                    <span class="text-xl">₱{{ number_format($total, 2) }}</span>
                </div>

                @if ($discount <= 0)
                    <p class="text-xs text-gray-500 pt-1">Enter a voucher code and click <strong>Apply voucher</strong> to preview your discount before placing the order.</p>
                @endif
            </div>

            <button
                type="submit"
                @disabled($unavailableItems->isNotEmpty())
                class="w-full mt-5 rounded-lg bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-gray-800 disabled:bg-gray-300 disabled:cursor-not-allowed transition cursor-pointer"
            >
                Place order &middot; ₱{{ number_format($total, 2) }}
            </button>
        </div>
    </aside>
</form>
@endsection
