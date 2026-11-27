<?php

namespace App\Modules\Orders\Http\Controllers;

use App\Contracts\OrderServiceInterface;
use App\Contracts\ShippingServiceInterface;
use App\Http\Controllers\Controller;
use App\Modules\Orders\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class AdminOrderController extends Controller
{
    public function index()
    {
        $orders = Order::with('user')->latest()->paginate(15);

        return view('orders::admin.index', compact('orders'));
    }

    public function show(Order $order, ShippingServiceInterface $shipping)
    {
        $order->loadMissing('items', 'user');
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
