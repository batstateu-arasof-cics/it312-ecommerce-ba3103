<?php

namespace App\Modules\Orders\Http\Controllers;

use App\Contracts\OrderServiceInterface;
use App\Contracts\ShippingServiceInterface;
use App\Http\Controllers\Controller;
use App\Modules\Orders\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class AdminOrderController extends Controller
{
    public function index(Request $request)
    {
        $validator = Validator::make($request->query(), [
            'status' => ['nullable', 'string', Rule::in(Order::STATUSES)],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $filterError = $validator->fails() ? $validator->errors()->first() : null;
        $filters = $validator->fails() ? [] : $validator->validated();

        $statusCounts = Order::selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $search = trim((string) ($filters['search'] ?? ''));

        $orders = Order::with(['user', 'payment'])
            ->withCount('items')
            ->when(! empty($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->when(! empty($filters['date_from']), fn ($q) => $q->whereDate('created_at', '>=', $filters['date_from']))
            ->when(! empty($filters['date_to']), fn ($q) => $q->whereDate('created_at', '<=', $filters['date_to']))
            ->when($search !== '', function ($q) use ($search) {
                $normalizedId = ltrim(str_ireplace('ORD-', '', $search), '0');

                $q->where(function ($sub) use ($search, $normalizedId) {
                    if ($normalizedId !== '' && ctype_digit($normalizedId)) {
                        $sub->orWhere('id', (int) $normalizedId);
                    }

                    $sub->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('orders::admin.index', [
            'orders' => $orders,
            'filters' => [
                'status' => $filters['status'] ?? '',
                'date_from' => $filters['date_from'] ?? '',
                'date_to' => $filters['date_to'] ?? '',
                'search' => $search,
            ],
            'filterError' => $filterError,
            'statusCounts' => $statusCounts,
            'totalOrdersCount' => (int) $statusCounts->sum(),
        ]);
    }

    public function show(Order $order, ShippingServiceInterface $shipping)
    {
        $order->loadMissing('items.product', 'user');
        $payment = $order->payment()->latest()->first();
        $shipment = $shipping->getTracking($order->id);

        return view('orders::admin.show', compact('order', 'payment', 'shipment'));
    }

    public function updateStatus(Request $request, Order $order, OrderServiceInterface $orders)
    {
        $data = $request->validate([
            'status' => ['required', 'string', Rule::in(Order::STATUSES)],
        ]);

        try {
            $orders->updateStatus($order->id, $data['status']);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Order {$order->number()} updated to {$data['status']}.");
    }
}
