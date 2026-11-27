<?php

namespace App\Modules\Orders\Http\Controllers;

use App\Contracts\CartServiceInterface;
use App\Contracts\CatalogServiceInterface;
use App\Contracts\OrderServiceInterface;
use App\Contracts\PaymentServiceInterface;
use App\Contracts\ShippingServiceInterface;
use App\Contracts\UserServiceInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use RuntimeException;

class CheckoutController extends Controller
{
    public function show(
        Request $request,
        CartServiceInterface $cart,
        UserServiceInterface $users,
        ShippingServiceInterface $shipping,
    ) {
        $userId = $request->user()->id;
        $items = $cart->getItems($userId);

        if ($items->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Your cart is empty.');
        }

        $address = $users->getDefaultAddress($userId);
        $subtotal = $cart->getSubtotal($userId);
        $shippingFee = $shipping->getShippingFee($address);

        return view('orders::checkout', compact('items', 'address', 'subtotal', 'shippingFee'));
    }

    public function store(
        Request $request,
        CartServiceInterface $cart,
        CatalogServiceInterface $catalog,
        OrderServiceInterface $orders,
        PaymentServiceInterface $payments,
    ) {
        $data = $request->validate([
            'payment_method' => ['required', 'in:cod,online'],
            'voucher_code' => ['nullable', 'string', 'max:50'],
        ]);

        $userId = $request->user()->id;
        $items = $cart->getItems($userId);

        if ($items->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Your cart is empty.');
        }

        foreach ($items as $item) {
            if (! $catalog->isAvailable($item->product_id, $item->quantity)) {
                $name = $item->product?->name ?? 'One of the items in your cart';

                return redirect()->route('cart.index')->with('error', "{$name} does not have enough stock available.");
            }
        }

        try {
            $order = $orders->createFromCart($userId, $data);
        } catch (RuntimeException $e) {
            return redirect()->route('cart.index')->with('error', $e->getMessage());
        }

        $payment = $payments->createPayment($order, $data['payment_method']);

        if ($payment->checkout_url) {
            return redirect()->away($payment->checkout_url); // online payment (Payments team)
        }

        return redirect()->route('orders.index')->with('success', 'Order '.$order->number().' placed.');
    }
}
