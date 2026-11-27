@php
    $steps = [
        \App\Modules\Orders\Models\Order::PENDING => 'Order placed',
        \App\Modules\Orders\Models\Order::PAID => 'Payment confirmed',
        \App\Modules\Orders\Models\Order::SHIPPED => 'Shipped',
        \App\Modules\Orders\Models\Order::COMPLETED => 'Completed',
    ];
    $stepKeys = array_keys($steps);
    $currentStepIndex = array_search($order->status, $stepKeys, true);
    $totalUnits = (int) $order->items->sum('quantity');
@endphp

{{-- Order progress timeline --}}
<section class="bg-white border border-gray-200 rounded-xl shadow-sm p-4 sm:p-5 mb-6">
    @if ($order->status === \App\Modules\Orders\Models\Order::CANCELLED)
        <div class="flex items-start gap-3 rounded-lg bg-rose-50 border border-rose-200 p-3.5 text-rose-800">
            <svg class="w-5 h-5 mt-0.5 shrink-0 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
            </svg>
            <div>
                <p class="font-semibold text-sm">This order has been cancelled</p>
                <p class="text-xs text-rose-700 mt-0.5">No further processing or shipment will take place for this order.</p>
            </div>
        </div>
    @else
        <h2 class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-4">Order progress</h2>
        <ol class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            @foreach ($steps as $key => $label)
                @php
                    $index = $loop->index;
                    $isDone = $currentStepIndex !== false && $index < $currentStepIndex;
                    $isCurrent = $currentStepIndex !== false && $index === $currentStepIndex;
                @endphp
                <li class="flex items-center gap-2.5 rounded-lg border p-2.5 {{ $isCurrent ? 'border-gray-900 bg-gray-50' : ($isDone ? 'border-emerald-200 bg-emerald-50/50' : 'border-gray-200 bg-white') }}">
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-semibold {{ $isDone ? 'bg-emerald-600 text-white' : ($isCurrent ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-500') }}">
                        @if ($isDone)
                            &#10003;
                        @else
                            {{ $index + 1 }}
                        @endif
                    </span>
                    <div class="min-w-0">
                        <p class="text-xs font-semibold truncate {{ $isDone ? 'text-emerald-800' : ($isCurrent ? 'text-gray-900' : 'text-gray-500') }}">{{ $label }}</p>
                        <p class="text-[11px] capitalize {{ $isCurrent ? 'text-gray-600' : 'text-gray-400' }}">
                            {{ $isDone ? 'Done' : ($isCurrent ? 'Current' : 'Upcoming') }}
                        </p>
                    </div>
                </li>
            @endforeach
        </ol>
    @endif
</section>

<div class="grid lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        {{-- Ordered Items --}}
        <section class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
            <div class="flex items-center justify-between px-4 sm:px-5 py-3.5 border-b border-gray-100 bg-gray-50/60">
                <h2 class="font-semibold text-gray-900">Items ordered</h2>
                <span class="text-xs text-gray-500 font-medium">{{ $totalUnits }} {{ $totalUnits === 1 ? 'unit' : 'units' }}</span>
            </div>

            @if ($order->items->isEmpty())
                <div class="p-6 text-center text-sm text-gray-500">
                    No line items were recorded for this order.
                </div>
            @else
                {{-- Mobile item cards --}}
                <ul class="divide-y divide-gray-100 sm:hidden">
                    @foreach ($order->items as $item)
                        <li class="p-4 flex items-start justify-between gap-3">
                            <div class="flex items-start gap-3 min-w-0">
                                @if ($item->product)
                                    <img src="{{ $item->product->imageUrl() }}" alt="{{ $item->product_name }}" class="w-12 h-12 rounded-lg object-cover border border-gray-200 shrink-0">
                                @else
                                    <div class="w-12 h-12 rounded-lg bg-gray-100 border border-gray-200 flex items-center justify-center text-xs text-gray-400 shrink-0">Item</div>
                                @endif
                                <div class="min-w-0">
                                    <p class="font-medium text-sm text-gray-900 truncate">{{ $item->product_name }}</p>
                                    <p class="text-xs text-gray-500 mt-0.5">
                                        ₱{{ number_format((float) $item->unit_price, 2) }} &times; {{ $item->quantity }}
                                    </p>
                                </div>
                            </div>
                            <p class="text-sm font-semibold text-gray-900 shrink-0">₱{{ number_format($item->lineTotal(), 2) }}</p>
                        </li>
                    @endforeach
                </ul>

                {{-- Desktop table --}}
                <div class="hidden sm:block overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-left border-b border-gray-100 text-xs uppercase tracking-wider text-gray-500 bg-gray-50/40">
                            <tr>
                                <th class="px-5 py-3 font-medium">Product</th>
                                <th class="px-5 py-3 text-right font-medium">Qty</th>
                                <th class="px-5 py-3 text-right font-medium">Unit price</th>
                                <th class="px-5 py-3 text-right font-medium">Line total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($order->items as $item)
                                <tr class="hover:bg-gray-50/60">
                                    <td class="px-5 py-3.5">
                                        <div class="flex items-center gap-3">
                                            @if ($item->product)
                                                <img src="{{ $item->product->imageUrl() }}" alt="{{ $item->product_name }}" class="w-10 h-10 rounded-lg object-cover border border-gray-200 shrink-0">
                                            @endif
                                            <span class="font-medium text-gray-900">{{ $item->product_name }}</span>
                                        </div>
                                    </td>
                                    <td class="px-5 py-3.5 text-right text-gray-700">{{ $item->quantity }}</td>
                                    <td class="px-5 py-3.5 text-right text-gray-700">₱{{ number_format((float) $item->unit_price, 2) }}</td>
                                    <td class="px-5 py-3.5 text-right font-semibold text-gray-900">₱{{ number_format($item->lineTotal(), 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        {{-- Shipping & Tracking --}}
        <section class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-4 sm:px-5 py-3.5 border-b border-gray-100 bg-gray-50/60">
                <h2 class="font-semibold text-gray-900">Shipping &amp; Tracking</h2>
            </div>

            <div class="p-4 sm:p-5 space-y-4">
                <div class="rounded-lg bg-gray-50 border border-gray-100 p-3.5">
                    <p class="text-xs font-medium uppercase tracking-wider text-gray-500 mb-1">Delivery address</p>
                    <p class="text-sm text-gray-800">{{ $order->shipping_address ?: 'No shipping address provided.' }}</p>
                </div>

                @if ($shipment)
                    <dl class="grid sm:grid-cols-2 gap-3 text-sm pt-1">
                        <div class="rounded-lg border border-gray-100 p-3">
                            <dt class="text-xs text-gray-500">Courier</dt>
                            <dd class="font-medium text-gray-900 mt-0.5">{{ $shipment->courier ?? 'N/A' }}</dd>
                        </div>
                        <div class="rounded-lg border border-gray-100 p-3">
                            <dt class="text-xs text-gray-500">Tracking number</dt>
                            <dd class="font-mono font-medium text-gray-900 mt-0.5">{{ $shipment->tracking_number ?? 'N/A' }}</dd>
                        </div>
                        <div class="rounded-lg border border-gray-100 p-3">
                            <dt class="text-xs text-gray-500 mb-1">Shipment status</dt>
                            <dd>
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-indigo-50 text-indigo-800 ring-1 ring-inset ring-indigo-200 capitalize">{{ $shipment->status }}</span>
                            </dd>
                        </div>
                        @if ($shipment->shipped_at || $shipment->delivered_at)
                            <div class="rounded-lg border border-gray-100 p-3 space-y-1">
                                @if ($shipment->shipped_at)
                                    <p class="text-xs text-gray-500">Shipped: <span class="text-gray-900 font-medium">{{ $shipment->shipped_at->format('M d, Y g:i A') }}</span></p>
                                @endif
                                @if ($shipment->delivered_at)
                                    <p class="text-xs text-gray-500">Delivered: <span class="text-gray-900 font-medium">{{ $shipment->delivered_at->format('M d, Y g:i A') }}</span></p>
                                @endif
                            </div>
                        @endif
                    </dl>
                @else
                    <div class="flex items-center gap-2.5 text-sm text-gray-500 border-t border-gray-100 pt-3">
                        <span class="h-2 w-2 rounded-full bg-gray-300" aria-hidden="true"></span>
                        <span>Not shipped yet. Tracking details will appear once dispatched.</span>
                    </div>
                @endif
            </div>
        </section>
    </div>

    <aside class="space-y-6">
        {{-- Order summary --}}
        <section class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-4 sm:px-5 py-3.5 border-b border-gray-100 bg-gray-50/60">
                <h2 class="font-semibold text-gray-900">Order summary</h2>
            </div>

            <div class="p-4 sm:p-5 text-sm space-y-2.5">
                <div class="flex justify-between text-gray-600">
                    <span>Items subtotal ({{ $totalUnits }})</span>
                    <span class="text-gray-900 font-medium">₱{{ number_format((float) $order->subtotal, 2) }}</span>
                </div>

                @if ((float) $order->discount > 0)
                    <div class="flex justify-between items-center text-emerald-700">
                        <span class="flex items-center gap-1.5">
                            <span>Voucher discount</span>
                            @if ($order->voucher_code)
                                <span class="rounded bg-emerald-50 px-1.5 py-0.5 text-[11px] font-mono font-semibold text-emerald-800 border border-emerald-200">{{ $order->voucher_code }}</span>
                            @endif
                        </span>
                        <span class="font-medium">-₱{{ number_format((float) $order->discount, 2) }}</span>
                    </div>
                @endif

                <div class="flex justify-between text-gray-600">
                    <span>Shipping fee</span>
                    <span class="text-gray-900 font-medium">₱{{ number_format((float) $order->shipping_fee, 2) }}</span>
                </div>

                <div class="flex justify-between items-baseline font-bold text-base text-gray-900 border-t border-gray-200 pt-3 mt-2">
                    <span>Total</span>
                    <span class="text-lg">₱{{ number_format((float) $order->total, 2) }}</span>
                </div>
            </div>
        </section>

        {{-- Payment --}}
        <section class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-4 sm:px-5 py-3.5 border-b border-gray-100 bg-gray-50/60">
                <h2 class="font-semibold text-gray-900">Payment details</h2>
            </div>

            <div class="p-4 sm:p-5 text-sm space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-gray-600">Method</span>
                    <span class="font-medium text-gray-900">{{ $order->paymentMethodLabel() }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-gray-600">Payment status</span>
                    @include('orders::partials.status-badge', ['status' => $payment?->status ?? 'pending'])
                </div>
                @if ($payment?->gateway_reference)
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-gray-600">Reference</span>
                        <span class="font-mono text-xs text-gray-700 truncate">{{ $payment->gateway_reference }}</span>
                    </div>
                @endif
                @if ($payment?->paid_at)
                    <div class="flex items-center justify-between">
                        <span class="text-gray-600">Paid at</span>
                        <span class="text-gray-900">{{ $payment->paid_at->format('M d, Y g:i A') }}</span>
                    </div>
                @endif
            </div>
        </section>
    </aside>
</div>
