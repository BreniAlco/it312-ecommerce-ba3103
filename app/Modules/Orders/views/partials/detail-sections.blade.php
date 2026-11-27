<div class="grid md:grid-cols-3 gap-6">
    <div class="md:col-span-2 space-y-6">
        <section class="bg-white rounded shadow p-4">
            <h2 class="font-semibold mb-3">Items</h2>
            <table class="w-full text-sm">
                <thead class="text-left border-b text-gray-500">
                    <tr>
                        <th class="py-2">Product</th>
                        <th class="py-2 text-right">Qty</th>
                        <th class="py-2 text-right">Unit price</th>
                        <th class="py-2 text-right">Line total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($order->items as $item)
                        <tr class="border-b last:border-b-0">
                            <td class="py-2 font-medium">{{ $item->product_name }}</td>
                            <td class="py-2 text-right">{{ $item->quantity }}</td>
                            <td class="py-2 text-right">₱{{ number_format((float) $item->unit_price, 2) }}</td>
                            <td class="py-2 text-right">₱{{ number_format((float) $item->unit_price * $item->quantity, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>

        <section class="bg-white rounded shadow p-4">
            <h2 class="font-semibold mb-2">Shipping &amp; Tracking</h2>
            <p class="text-sm text-gray-700 mb-3">
                <span class="font-medium">Delivery address:</span>
                {{ $order->shipping_address ?: 'No shipping address provided.' }}
            </p>
            @if ($shipment)
                <div class="text-sm space-y-1 border-t pt-3">
                    <p><span class="text-gray-500">Courier:</span> {{ $shipment->courier ?? 'N/A' }}</p>
                    <p><span class="text-gray-500">Tracking number:</span> {{ $shipment->tracking_number ?? 'N/A' }}</p>
                    <p><span class="text-gray-500">Shipment status:</span> <span class="capitalize">{{ $shipment->status }}</span></p>
                    @if ($shipment->shipped_at)
                        <p><span class="text-gray-500">Shipped at:</span> {{ $shipment->shipped_at->format('M d, Y g:i A') }}</p>
                    @endif
                    @if ($shipment->delivered_at)
                        <p><span class="text-gray-500">Delivered at:</span> {{ $shipment->delivered_at->format('M d, Y g:i A') }}</p>
                    @endif
                </div>
            @else
                <p class="text-sm text-gray-500 border-t pt-3">Not shipped yet.</p>
            @endif
        </section>
    </div>

    <aside class="space-y-6">
        <section class="bg-white rounded shadow p-4">
            <h2 class="font-semibold mb-3">Order summary</h2>
            <div class="text-sm space-y-2">
                <p class="flex justify-between">
                    <span class="text-gray-600">Subtotal</span>
                    <span>₱{{ number_format((float) $order->subtotal, 2) }}</span>
                </p>
                @if ((float) $order->discount > 0)
                    <p class="flex justify-between text-green-700">
                        <span>Discount{{ $order->voucher_code ? " ({$order->voucher_code})" : '' }}</span>
                        <span>-₱{{ number_format((float) $order->discount, 2) }}</span>
                    </p>
                @endif
                <p class="flex justify-between">
                    <span class="text-gray-600">Shipping fee</span>
                    <span>₱{{ number_format((float) $order->shipping_fee, 2) }}</span>
                </p>
                <p class="flex justify-between font-bold text-base border-t pt-2">
                    <span>Total</span>
                    <span>₱{{ number_format((float) $order->total, 2) }}</span>
                </p>
            </div>
        </section>

        <section class="bg-white rounded shadow p-4">
            <h2 class="font-semibold mb-3">Payment</h2>
            <div class="text-sm space-y-1">
                <p class="flex justify-between">
                    <span class="text-gray-600">Method</span>
                    <span class="uppercase font-medium">{{ $order->payment_method }}</span>
                </p>
                <p class="flex justify-between">
                    <span class="text-gray-600">Payment status</span>
                    <span class="capitalize font-medium">{{ $payment?->status ?? 'pending' }}</span>
                </p>
                @if ($payment?->paid_at)
                    <p class="flex justify-between">
                        <span class="text-gray-600">Paid at</span>
                        <span>{{ $payment->paid_at->format('M d, Y g:i A') }}</span>
                    </p>
                @endif
            </div>
        </section>
    </aside>
</div>
