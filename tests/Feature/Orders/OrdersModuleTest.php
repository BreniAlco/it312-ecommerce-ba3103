<?php

namespace Tests\Feature\Orders;

use App\Contracts\OrderServiceInterface;
use App\Events\OrderCancelled;
use App\Events\OrderPlaced;
use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Orders\Models\Order;
use App\Modules\Payments\Models\Payment;
use App\Modules\Shipping\Models\Shipment;
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
            ->assertSessionHas('error');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('cart_items', 1);
        Event::assertNotDispatched(OrderPlaced::class);

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
}
