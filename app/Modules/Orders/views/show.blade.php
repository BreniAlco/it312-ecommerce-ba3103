@extends('layouts.app')

@section('title', $order->number())

@section('content')
<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
        <a href="{{ route('orders.index') }}" class="text-sm text-gray-500 hover:underline">&larr; Back to my orders</a>
        <h1 class="text-2xl font-bold mt-1">{{ $order->number() }}</h1>
        <p class="text-sm text-gray-500">Placed on {{ $order->created_at->format('M d, Y g:i A') }}</p>
    </div>

    <div class="flex items-center gap-3">
        <span class="px-3 py-1 rounded-full text-sm font-medium capitalize bg-gray-100 text-gray-800">
            {{ $order->status }}
        </span>

        @if ($order->status === \App\Modules\Orders\Models\Order::PENDING)
            <form method="POST" action="{{ route('orders.cancel', $order) }}" onsubmit="return confirm('Cancel this order?');">
                @csrf
                <button type="submit" class="px-4 py-2 rounded bg-red-600 text-white text-sm font-medium hover:bg-red-700">
                    Cancel order
                </button>
            </form>
        @endif
    </div>
</div>

@include('orders::partials.detail-sections', ['order' => $order, 'payment' => $payment, 'shipment' => $shipment])
@endsection
