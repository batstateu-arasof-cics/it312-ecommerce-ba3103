<?php

namespace App\Modules\Orders\Http\Controllers;

use App\Contracts\OrderServiceInterface;
use App\Contracts\ShippingServiceInterface;
use App\Http\Controllers\Controller;
use App\Modules\Orders\Models\Order;
use Illuminate\Http\Request;
use InvalidArgumentException;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->id;
        $requestedStatus = $request->query('status');
        $activeStatus = in_array($requestedStatus, Order::STATUSES, true) ? $requestedStatus : null;

        $statusCounts = Order::where('user_id', $userId)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $totalOrdersCount = (int) $statusCounts->sum();

        $orders = Order::where('user_id', $userId)
            ->withCount('items')
            ->with('payment')
            ->when($activeStatus, fn ($query) => $query->where('status', $activeStatus))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('orders::index', compact('orders', 'statusCounts', 'totalOrdersCount', 'activeStatus'));
    }

    public function show(Request $request, Order $order, ShippingServiceInterface $shipping)
    {
        abort_if($order->user_id !== $request->user()->id, 403);

        $order->loadMissing('items.product');
        $payment = $order->payment()->latest()->first();
        $shipment = $shipping->getTracking($order->id);

        return view('orders::show', compact('order', 'payment', 'shipment'));
    }

    public function cancel(Request $request, Order $order, OrderServiceInterface $orders)
    {
        abort_if($order->user_id !== $request->user()->id, 403);

        if ($order->status !== Order::PENDING) {
            return redirect()
                ->route('orders.show', $order)
                ->with('error', 'Only pending orders can be cancelled.');
        }

        try {
            $orders->updateStatus($order->id, Order::CANCELLED);
        } catch (InvalidArgumentException $e) {
            return redirect()->route('orders.show', $order)->with('error', $e->getMessage());
        }

        return redirect()
            ->route('orders.show', $order)
            ->with('success', "Order {$order->number()} cancelled.");
    }
}
