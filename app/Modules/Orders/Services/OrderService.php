<?php

namespace App\Modules\Orders\Services;

use App\Contracts\CartServiceInterface;
use App\Contracts\CatalogServiceInterface;
use App\Contracts\OrderServiceInterface;
use App\Contracts\ShippingServiceInterface;
use App\Contracts\UserServiceInterface;
use App\Contracts\VoucherServiceInterface;
use App\Events\OrderCancelled;
use App\Events\OrderPlaced;
use App\Modules\Orders\Models\Order;
use App\Modules\Users\Models\Address;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class OrderService implements OrderServiceInterface
{
    public function __construct(
        private CartServiceInterface $cart,
        private CatalogServiceInterface $catalog,
        private UserServiceInterface $users,
        private VoucherServiceInterface $vouchers,
        private ShippingServiceInterface $shipping,
    ) {}

    public function createFromCart(int $userId, array $checkout = []): Order
    {
        $paymentMethod = $checkout['payment_method'] ?? 'cod';
        if (! in_array($paymentMethod, Order::PAYMENT_METHODS, true)) {
            throw new InvalidArgumentException("Unsupported payment method: {$paymentMethod}");
        }

        $addressId = $checkout['address_id'] ?? null;
        if ($addressId !== null && $addressId !== '') {
            $address = Address::where('user_id', $userId)->find($addressId);
            if (! $address) {
                throw new InvalidArgumentException('Selected shipping address was not found.');
            }
        } else {
            $address = $this->users->getDefaultAddress($userId);
        }

        $rawVoucher = $checkout['voucher_code'] ?? null;
        $voucherCode = filled($rawVoucher) ? strtoupper(trim((string) $rawVoucher)) : null;

        $order = DB::transaction(function () use ($userId, $address, $voucherCode, $paymentMethod) {
            $items = $this->cart->getItems($userId);

            if ($items->isEmpty()) {
                throw new RuntimeException('The cart is empty.');
            }

            foreach ($items as $item) {
                if (! $item->product || ! $this->catalog->isAvailable($item->product_id, $item->quantity)) {
                    $name = $item->product?->name ?? 'One of the items in your cart';

                    throw new RuntimeException("{$name} does not have enough stock available.");
                }
            }

            $subtotal = round($this->cart->getSubtotal($userId), 2);
            $discount = $voucherCode
                ? round(min(max(0.0, $this->vouchers->apply($voucherCode, $subtotal)), $subtotal), 2)
                : 0.0;
            $shippingFee = round(max(0.0, $this->shipping->getShippingFee($address)), 2);
            $total = round(max(0.0, $subtotal - $discount) + $shippingFee, 2);

            $order = Order::create([
                'user_id' => $userId,
                'status' => Order::PENDING,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'shipping_fee' => $shippingFee,
                'total' => $total,
                'voucher_code' => $discount > 0 ? $voucherCode : null,
                'payment_method' => $paymentMethod,
                'address_id' => $address?->id,
                'shipping_address' => $address?->fullAddress(),
            ]);

            foreach ($items as $item) {
                $order->items()->create([
                    'product_id' => $item->product_id,
                    'product_name' => $item->product->name,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->product->price,
                ]);
            }

            $this->cart->clear($userId);

            return $order;
        });

        // Other modules react to this (stock, voucher, email, shipment)
        OrderPlaced::dispatch($order, $order->voucher_code);

        return $order;
    }

    public function find(int $orderId): ?Order
    {
        return Order::with('items')->find($orderId);
    }

    public function updateStatus(int $orderId, string $status): Order
    {
        if (! in_array($status, Order::STATUSES, true)) {
            throw new InvalidArgumentException("Unknown order status: {$status}");
        }

        $order = DB::transaction(function () use ($orderId, $status) {
            $order = Order::lockForUpdate()->findOrFail($orderId);

            if (! $order->canTransitionTo($status)) {
                throw new InvalidArgumentException("Cannot transition order from {$order->status} to {$status}.");
            }

            $order->update(['status' => $status]);

            return $order;
        });

        if ($status === Order::CANCELLED) {
            OrderCancelled::dispatch($order);
        }

        return $order;
    }

    public function hasPurchased(int $userId, int $productId): bool
    {
        return Order::where('user_id', $userId)
            ->whereIn('status', [Order::PAID, Order::SHIPPED, Order::COMPLETED])
            ->whereHas('items', fn ($q) => $q->where('product_id', $productId))
            ->exists();
    }
}
