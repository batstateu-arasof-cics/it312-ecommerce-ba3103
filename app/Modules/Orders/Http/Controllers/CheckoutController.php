<?php

namespace App\Modules\Orders\Http\Controllers;

use App\Contracts\CartServiceInterface;
use App\Contracts\CatalogServiceInterface;
use App\Contracts\OrderServiceInterface;
use App\Contracts\PaymentServiceInterface;
use App\Contracts\ShippingServiceInterface;
use App\Contracts\UserServiceInterface;
use App\Contracts\VoucherServiceInterface;
use App\Http\Controllers\Controller;
use App\Modules\Orders\Models\Order;
use App\Modules\Users\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use RuntimeException;

class CheckoutController extends Controller
{
    public function show(
        Request $request,
        CartServiceInterface $cart,
        CatalogServiceInterface $catalog,
        UserServiceInterface $users,
        ShippingServiceInterface $shipping,
        VoucherServiceInterface $vouchers,
    ) {
        $userId = $request->user()->id;
        $items = $cart->getItems($userId);

        if ($items->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Your cart is empty.');
        }

        $addresses = Address::where('user_id', $userId)
            ->orderByDesc('is_default')
            ->latest()
            ->get();

        $defaultAddress = $users->getDefaultAddress($userId) ?? $addresses->first();
        $requestedAddressId = old('address_id', $request->query('address_id'));

        $address = ($requestedAddressId !== null && $requestedAddressId !== '')
            ? ($addresses->firstWhere('id', (int) $requestedAddressId) ?? $defaultAddress)
            : $defaultAddress;

        $subtotal = round($cart->getSubtotal($userId), 2);
        $rawVoucher = old('voucher_code', $request->query('voucher_code'));
        $voucherCode = filled($rawVoucher) ? strtoupper(trim((string) $rawVoucher)) : null;
        $discount = $voucherCode
            ? round(min(max(0.0, $vouchers->apply($voucherCode, $subtotal)), $subtotal), 2)
            : 0.0;

        $voucherError = ($voucherCode !== null && $discount <= 0.0 && ! $request->session()->has('errors'))
            ? "Voucher code \"{$voucherCode}\" is invalid, expired, or does not meet the minimum spend."
            : null;

        $shippingFee = round(max(0.0, $shipping->getShippingFee($address)), 2);
        $total = round(max(0.0, $subtotal - $discount) + $shippingFee, 2);

        $selectedPaymentMethod = old('payment_method', $request->query('payment_method', 'cod'));
        if (! in_array($selectedPaymentMethod, Order::PAYMENT_METHODS, true)) {
            $selectedPaymentMethod = 'cod';
        }

        $unavailableItems = $items->filter(
            fn ($item) => ! $item->product || ! $catalog->isAvailable($item->product_id, $item->quantity)
        );

        return view('orders::checkout', compact(
            'items',
            'addresses',
            'address',
            'subtotal',
            'discount',
            'voucherCode',
            'voucherError',
            'shippingFee',
            'total',
            'selectedPaymentMethod',
            'unavailableItems',
        ));
    }

    public function store(
        Request $request,
        CartServiceInterface $cart,
        CatalogServiceInterface $catalog,
        OrderServiceInterface $orders,
        PaymentServiceInterface $payments,
        VoucherServiceInterface $vouchers,
    ) {
        $userId = $request->user()->id;

        $data = $request->validate([
            'payment_method' => ['required', Rule::in(Order::PAYMENT_METHODS)],
            'voucher_code' => ['nullable', 'string', 'max:50'],
            'address_id' => ['nullable', 'integer', Rule::exists('addresses', 'id')->where('user_id', $userId)],
        ]);

        $items = $cart->getItems($userId);

        if ($items->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Your cart is empty.');
        }

        $voucherCode = filled($data['voucher_code'] ?? null)
            ? strtoupper(trim((string) $data['voucher_code']))
            : null;
        $data['voucher_code'] = $voucherCode;

        if ($voucherCode !== null) {
            $subtotal = round($cart->getSubtotal($userId), 2);
            if ($vouchers->apply($voucherCode, $subtotal) <= 0.0) {
                return back()
                    ->withInput()
                    ->withErrors(['voucher_code' => "Voucher code \"{$voucherCode}\" is invalid, expired, or does not meet the minimum spend."]);
            }
        }

        foreach ($items as $item) {
            if (! $item->product || ! $catalog->isAvailable($item->product_id, $item->quantity)) {
                $name = $item->product?->name ?? 'One of the items in your cart';

                return redirect()->route('cart.index')->with('error', "{$name} does not have enough stock available.");
            }
        }

        try {
            $order = $orders->createFromCart($userId, $data);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (RuntimeException $e) {
            return redirect()->route('cart.index')->with('error', $e->getMessage());
        }

        $payment = $payments->createPayment($order, $data['payment_method']);

        if ($payment->checkout_url) {
            return redirect()->away($payment->checkout_url); // online payment (Payments team)
        }

        $message = $data['payment_method'] === 'online'
            ? "Order {$order->number()} placed. Online payment gateway is currently unavailable, so your order remains pending."
            : "Order {$order->number()} placed.";

        return redirect()->route('orders.index')->with('success', $message);
    }
}
