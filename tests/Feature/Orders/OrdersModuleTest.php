<?php

namespace Tests\Feature\Orders;

use App\Contracts\OrderServiceInterface;
use App\Events\OrderCancelled;
use App\Events\OrderPlaced;
use App\Events\OrderShipped;
use App\Events\PaymentConfirmed;
use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Orders\Models\Order;
use App\Modules\Payments\Models\Payment;
use App\Modules\Shipping\Models\Shipment;
use App\Modules\Users\Models\Address;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class OrdersModuleTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    public function test_checkout_calculates_totals_with_voucher_and_shipping(): void
    {
        $customer = User::where('email', 'customer@shop.test')->firstOrFail();
        $product = Product::firstOrFail(); // Kapeng Barako 250g, price 220

        $this->actingAs($customer)
            ->post(route('cart.add', $product), ['quantity' => 2]);

        $this->actingAs($customer)
            ->post(route('checkout.store'), [
                'payment_method' => 'cod',
                'voucher_code' => 'WELCOME10',
            ])
            ->assertRedirect(route('orders.index'));

        $order = Order::where('user_id', $customer->id)->firstOrFail();
        $expectedSubtotal = (float) $product->price * 2; // 440.00
        $expectedDiscount = round($expectedSubtotal * 0.10, 2); // 44.00
        $expectedShipping = 150.00;
        $expectedTotal = $expectedSubtotal - $expectedDiscount + $expectedShipping; // 546.00

        $this->assertEquals($expectedSubtotal, (float) $order->subtotal);
        $this->assertEquals($expectedDiscount, (float) $order->discount);
        $this->assertEquals($expectedShipping, (float) $order->shipping_fee);
        $this->assertEquals($expectedTotal, (float) $order->total);
        $this->assertSame('WELCOME10', $order->voucher_code);
    }

    public function test_checkout_rejects_empty_cart(): void
    {
        $customer = User::where('email', 'customer@shop.test')->firstOrFail();

        $this->actingAs($customer)
            ->post(route('checkout.store'), ['payment_method' => 'cod'])
            ->assertRedirect(route('cart.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('orders', 0);

        $this->expectException(RuntimeException::class);
        app(OrderServiceInterface::class)->createFromCart($customer->id, ['payment_method' => 'cod']);
    }

    public function test_checkout_rechecks_stock_before_placing_order(): void
    {
        $customer = User::where('email', 'customer@shop.test')->firstOrFail();
        $product = Product::firstOrFail();

        $this->actingAs($customer)
            ->post(route('cart.add', $product), ['quantity' => 5]);

        // Stock drops after the item was added to cart
        $product->update(['stock' => 2]);

        Event::fake([OrderPlaced::class]);

        $this->actingAs($customer)
            ->post(route('checkout.store'), ['payment_method' => 'cod'])
            ->assertRedirect(route('cart.index'))
            ->assertSessionHas('error', fn (string $message) => str_contains($message, $product->name));

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('cart_items', 1);
        Event::assertNotDispatched(OrderPlaced::class);

        // Zero stock is also blocked
        $product->update(['stock' => 0]);
        $this->actingAs($customer)
            ->post(route('checkout.store'), ['payment_method' => 'cod'])
            ->assertRedirect(route('cart.index'))
            ->assertSessionHas('error', fn (string $message) => str_contains($message, $product->name));

        $this->expectException(RuntimeException::class);
        app(OrderServiceInterface::class)->createFromCart($customer->id, ['payment_method' => 'cod']);
    }

    public function test_customer_can_view_own_order_detail_with_tracking_and_payment_status(): void
    {
        $customer = User::where('email', 'customer@shop.test')->firstOrFail();
        $otherUser = User::factory()->create(['role' => 'customer']);
        $product = Product::firstOrFail();

        $this->actingAs($customer)->post(route('cart.add', $product), ['quantity' => 1]);
        $this->actingAs($customer)->post(route('checkout.store'), ['payment_method' => 'cod']);

        $order = Order::where('user_id', $customer->id)->firstOrFail();
        $order->update(['shipping_address' => '123 Rizal St, Batangas City']);

        Payment::where('order_id', $order->id)->update(['status' => Payment::PAID]);
        Shipment::updateOrCreate(
            ['order_id' => $order->id],
            ['courier' => 'J&T Express', 'tracking_number' => 'JT-998877', 'status' => 'in_transit']
        );

        $this->actingAs($customer)
            ->get(route('orders.show', $order))
            ->assertOk()
            ->assertSee($order->number())
            ->assertSee($product->name)
            ->assertSee('123 Rizal St, Batangas City')
            ->assertSee('paid')
            ->assertSee('J&T Express')
            ->assertSee('JT-998877');

        $this->actingAs($otherUser)
            ->get(route('orders.show', $order))
            ->assertForbidden();
    }

    public function test_customer_can_cancel_pending_order_and_fires_event(): void
    {
        $customer = User::where('email', 'customer@shop.test')->firstOrFail();
        $otherUser = User::factory()->create(['role' => 'customer']);
        $product = Product::firstOrFail();

        $this->actingAs($customer)->post(route('cart.add', $product), ['quantity' => 2]);
        $this->actingAs($customer)->post(route('checkout.store'), ['payment_method' => 'cod']);

        $order = Order::where('user_id', $customer->id)->firstOrFail();

        $this->actingAs($otherUser)
            ->post(route('orders.cancel', $order))
            ->assertForbidden();

        Event::fake([OrderCancelled::class]);

        $this->actingAs($customer)
            ->post(route('orders.cancel', $order))
            ->assertRedirect(route('orders.show', $order))
            ->assertSessionHas('success');

        $this->assertSame(Order::CANCELLED, $order->fresh()->status);
        Event::assertDispatched(OrderCancelled::class, fn (OrderCancelled $event) => $event->order->id === $order->id);
    }

    public function test_customer_cannot_cancel_non_pending_order(): void
    {
        $customer = User::where('email', 'customer@shop.test')->firstOrFail();
        $product = Product::firstOrFail();

        $this->actingAs($customer)->post(route('cart.add', $product), ['quantity' => 1]);
        $this->actingAs($customer)->post(route('checkout.store'), ['payment_method' => 'cod']);

        $order = Order::where('user_id', $customer->id)->firstOrFail();
        $order->update(['status' => Order::PAID]);

        Event::fake([OrderCancelled::class]);

        $this->actingAs($customer)
            ->post(route('orders.cancel', $order))
            ->assertRedirect(route('orders.show', $order))
            ->assertSessionHas('error');

        $this->assertSame(Order::PAID, $order->fresh()->status);
        Event::assertNotDispatched(OrderCancelled::class);
    }

    public function test_update_status_enforces_transitions_and_fires_order_cancelled(): void
    {
        $customer = User::where('email', 'customer@shop.test')->firstOrFail();
        $product = Product::firstOrFail();

        $this->actingAs($customer)->post(route('cart.add', $product), ['quantity' => 1]);
        $this->actingAs($customer)->post(route('checkout.store'), ['payment_method' => 'cod']);

        $order = Order::where('user_id', $customer->id)->firstOrFail();
        $service = app(OrderServiceInterface::class);

        $service->updateStatus($order->id, Order::PAID);
        $service->updateStatus($order->id, Order::SHIPPED);
        $service->updateStatus($order->id, Order::COMPLETED);
        $this->assertSame(Order::COMPLETED, $order->fresh()->status);

        $this->expectException(InvalidArgumentException::class);
        $service->updateStatus($order->id, Order::CANCELLED);
    }

    public function test_update_status_rejects_skipping_states(): void
    {
        $customer = User::where('email', 'customer@shop.test')->firstOrFail();
        $product = Product::firstOrFail();

        $this->actingAs($customer)->post(route('cart.add', $product), ['quantity' => 1]);
        $this->actingAs($customer)->post(route('checkout.store'), ['payment_method' => 'cod']);

        $order = Order::where('user_id', $customer->id)->firstOrFail();

        $this->expectException(InvalidArgumentException::class);
        app(OrderServiceInterface::class)->updateStatus($order->id, Order::COMPLETED);
    }

    public function test_admin_can_list_orders_and_update_status(): void
    {
        $customer = User::where('email', 'customer@shop.test')->firstOrFail();
        $admin = User::where('email', 'admin@shop.test')->firstOrFail();
        $product = Product::firstOrFail();

        $this->actingAs($customer)->post(route('cart.add', $product), ['quantity' => 1]);
        $this->actingAs($customer)->post(route('checkout.store'), ['payment_method' => 'cod']);

        $order = Order::where('user_id', $customer->id)->firstOrFail();

        $this->actingAs($customer)->get(route('admin.orders.index'))->assertForbidden();
        $this->actingAs($customer)->patch(route('admin.orders.status', $order), ['status' => Order::PAID])->assertForbidden();

        $this->actingAs($admin)
            ->get(route('admin.orders.index'))
            ->assertOk()
            ->assertSee($order->number())
            ->assertSee($customer->name);

        $this->actingAs($admin)
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee($order->number());

        $this->actingAs($admin)
            ->from(route('admin.orders.index'))
            ->patch(route('admin.orders.status', $order), ['status' => Order::PAID])
            ->assertRedirect(route('admin.orders.index'))
            ->assertSessionHas('success');

        $this->assertSame(Order::PAID, $order->fresh()->status);

        // Invalid transition through admin endpoint flashes error without changing status
        $this->actingAs($admin)
            ->from(route('admin.orders.index'))
            ->patch(route('admin.orders.status', $order), ['status' => Order::COMPLETED])
            ->assertRedirect(route('admin.orders.index'))
            ->assertSessionHas('error');

        $this->assertSame(Order::PAID, $order->fresh()->status);
    }

    public function test_checkout_previews_and_validates_voucher_codes(): void
    {
        $customer = User::where('email', 'customer@shop.test')->firstOrFail();
        $product = Product::firstOrFail(); // 220.00

        $this->actingAs($customer)->post(route('cart.add', $product), ['quantity' => 2]); // 440.00

        // Valid voucher preview on checkout page
        $this->actingAs($customer)
            ->get(route('checkout.show', ['voucher_code' => ' welcome10 ']))
            ->assertOk()
            ->assertSee('WELCOME10')
            ->assertSee('-₱44.00')
            ->assertSee('₱546.00');

        // Invalid voucher preview displays inline error without redirecting away
        $this->actingAs($customer)
            ->get(route('checkout.show', ['voucher_code' => 'INVALIDCODE']))
            ->assertOk()
            ->assertSee('is invalid, expired, or does not meet the minimum spend.');

        // Submitting an invalid voucher fails validation cleanly
        $this->actingAs($customer)
            ->from(route('checkout.show'))
            ->post(route('checkout.store'), [
                'payment_method' => 'cod',
                'voucher_code' => 'INVALIDCODE',
            ])
            ->assertRedirect(route('checkout.show'))
            ->assertSessionHasErrors('voucher_code');

        $this->assertDatabaseCount('orders', 0);

        // Submitting lowercase/padded valid voucher normalizes code on order
        $this->actingAs($customer)
            ->post(route('checkout.store'), [
                'payment_method' => 'cod',
                'voucher_code' => ' welcome10 ',
            ])
            ->assertRedirect(route('orders.index'));

        $order = Order::where('user_id', $customer->id)->firstOrFail();
        $this->assertSame('WELCOME10', $order->voucher_code);
        $this->assertEquals(44.00, (float) $order->discount);
    }

    public function test_checkout_address_picker_and_ownership_validation(): void
    {
        $customer = User::where('email', 'customer@shop.test')->firstOrFail();
        $otherUser = User::factory()->create(['role' => 'customer']);
        $product = Product::firstOrFail();

        $customerAddress = Address::create([
            'user_id' => $customer->id,
            'recipient' => 'Juan Dela Cruz',
            'phone' => '09171234567',
            'street' => '45 Mabini St',
            'barangay' => 'Brgy. 1',
            'city' => 'Nasugbu',
            'province' => 'Batangas',
            'region' => 'Region IV-A',
            'postal_code' => '4231',
            'is_default' => true,
        ]);

        $secondaryAddress = Address::create([
            'user_id' => $customer->id,
            'recipient' => 'Juan Office',
            'phone' => '09179998888',
            'street' => '10 Ayala Blvd',
            'barangay' => 'Brgy. 3',
            'city' => 'Lipa',
            'province' => 'Batangas',
            'region' => 'Region IV-A',
            'postal_code' => '4217',
            'is_default' => false,
        ]);

        $otherAddress = Address::create([
            'user_id' => $otherUser->id,
            'recipient' => 'Other User',
            'phone' => '09170000000',
            'street' => '99 Secret Ave',
            'barangay' => 'Brgy. 2',
            'city' => 'Makati',
            'province' => 'Metro Manila',
            'region' => 'NCR',
            'postal_code' => '1200',
            'is_default' => true,
        ]);

        $this->actingAs($customer)->post(route('cart.add', $product), ['quantity' => 1]);

        $this->actingAs($customer)
            ->get(route('checkout.show'))
            ->assertOk()
            ->assertSee('45 Mabini St, Brgy. 1, Nasugbu, Batangas, 4231')
            ->assertDontSee('99 Secret Ave');

        // Reject address belonging to another user
        $this->actingAs($customer)
            ->from(route('checkout.show'))
            ->post(route('checkout.store'), [
                'payment_method' => 'cod',
                'address_id' => $otherAddress->id,
            ])
            ->assertRedirect(route('checkout.show'))
            ->assertSessionHasErrors('address_id');

        $this->actingAs($customer)
            ->post(route('checkout.store'), [
                'payment_method' => 'cod',
                'address_id' => $secondaryAddress->id,
            ])
            ->assertRedirect(route('orders.index'));

        $order = Order::where('user_id', $customer->id)->firstOrFail();
        $this->assertSame($secondaryAddress->id, $order->address_id);
        $this->assertSame('10 Ayala Blvd, Brgy. 3, Lipa, Batangas, 4217', $order->shipping_address);
    }

    public function test_customer_orders_list_filters_by_status_and_shows_empty_states(): void
    {
        $customer = User::where('email', 'customer@shop.test')->firstOrFail();
        $product = Product::firstOrFail();

        $this->actingAs($customer)
            ->get(route('orders.index'))
            ->assertOk()
            ->assertSee('No orders yet');

        $this->actingAs($customer)->post(route('cart.add', $product), ['quantity' => 1]);
        $this->actingAs($customer)->post(route('checkout.store'), ['payment_method' => 'cod']);
        $pendingOrder = Order::where('user_id', $customer->id)->latest('id')->firstOrFail();

        $this->actingAs($customer)->post(route('cart.add', $product), ['quantity' => 1]);
        $this->actingAs($customer)->post(route('checkout.store'), ['payment_method' => 'cod']);
        $paidOrder = Order::where('user_id', $customer->id)->latest('id')->firstOrFail();
        $paidOrder->update(['status' => Order::PAID]);

        $this->actingAs($customer)
            ->get(route('orders.index', ['status' => Order::PAID]))
            ->assertOk()
            ->assertSee($paidOrder->number())
            ->assertDontSee($pendingOrder->number());

        $this->actingAs($customer)
            ->get(route('orders.index', ['status' => Order::SHIPPED]))
            ->assertOk()
            ->assertSee('No shipped orders found');
    }

    public function test_admin_orders_filters_by_status_date_and_search(): void
    {
        $customer = User::where('email', 'customer@shop.test')->firstOrFail();
        $otherCustomer = User::factory()->create(['name' => 'Maria Clara', 'email' => 'maria@shop.test', 'role' => 'customer']);
        $admin = User::where('email', 'admin@shop.test')->firstOrFail();
        $product = Product::firstOrFail();

        $this->actingAs($customer)->post(route('cart.add', $product), ['quantity' => 1]);
        $this->actingAs($customer)->post(route('checkout.store'), ['payment_method' => 'cod']);
        $juanOrder = Order::where('user_id', $customer->id)->firstOrFail();

        $this->actingAs($otherCustomer)->post(route('cart.add', $product), ['quantity' => 1]);
        $this->actingAs($otherCustomer)->post(route('checkout.store'), ['payment_method' => 'cod']);
        $mariaOrder = Order::where('user_id', $otherCustomer->id)->firstOrFail();
        $mariaOrder->update(['status' => Order::PAID]);
        Order::where('id', $mariaOrder->id)->update(['created_at' => now()->subDays(10)]);

        // Filter by status
        $this->actingAs($admin)
            ->get(route('admin.orders.index', ['status' => Order::PAID]))
            ->assertOk()
            ->assertSee($mariaOrder->number())
            ->assertDontSee($juanOrder->number());

        // Filter by search (order number)
        $this->actingAs($admin)
            ->get(route('admin.orders.index', ['search' => $juanOrder->number()]))
            ->assertOk()
            ->assertSee($juanOrder->number())
            ->assertDontSee($mariaOrder->number());

        // Filter by date range
        $this->actingAs($admin)
            ->get(route('admin.orders.index', [
                'date_from' => now()->subDay()->toDateString(),
                'date_to' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee($juanOrder->number())
            ->assertDontSee($mariaOrder->number());

        // Invalid date range stays on page with inline error
        $this->actingAs($admin)
            ->get(route('admin.orders.index', [
                'date_from' => now()->toDateString(),
                'date_to' => now()->subDays(5)->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('date to field must be a date after or equal to date from');
    }

    public function test_listeners_and_has_purchased_handle_order_lifecycle(): void
    {
        $customer = User::where('email', 'customer@shop.test')->firstOrFail();
        $product = Product::firstOrFail();

        $this->actingAs($customer)->post(route('cart.add', $product), ['quantity' => 1]);
        $this->actingAs($customer)->post(route('checkout.store'), ['payment_method' => 'cod']);

        $order = Order::where('user_id', $customer->id)->firstOrFail();
        $service = app(OrderServiceInterface::class);

        $this->assertFalse($service->hasPurchased($customer->id, $product->id));

        $payment = Payment::where('order_id', $order->id)->firstOrFail();
        $payment->update(['status' => Payment::PAID, 'paid_at' => now()]);
        PaymentConfirmed::dispatch($payment);

        $this->assertSame(Order::PAID, $order->fresh()->status);
        $this->assertTrue($service->hasPurchased($customer->id, $product->id));

        $shipment = Shipment::updateOrCreate(
            ['order_id' => $order->id],
            ['courier' => 'J&T Express', 'tracking_number' => 'JT-123456', 'status' => 'shipped']
        );
        OrderShipped::dispatch($shipment);

        $this->assertSame(Order::SHIPPED, $order->fresh()->status);
        $this->assertTrue($service->hasPurchased($customer->id, $product->id));
    }
}
