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
        $orders = Order::where('user_id', $request->user()->id)->latest()->paginate(10);

        return view('orders::index', compact('orders'));
    }

    public function show(Request $request, Order $order, ShippingServiceInterface $shipping)
    {
        abort_if($order->user_id !== $request->user()->id, 403);

        $order->loadMissing('items');
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
