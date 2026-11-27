@extends('layouts.app')

@section('title', 'Admin · '.$order->number())

@section('content')
@php
    $nextStatuses = \App\Modules\Orders\Models\Order::TRANSITIONS[$order->status] ?? [];
@endphp

<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
        <a href="{{ route('admin.orders.index') }}" class="text-sm text-gray-500 hover:underline">&larr; Back to all orders</a>
        <h1 class="text-2xl font-bold mt-1">{{ $order->number() }}</h1>
        <p class="text-sm text-gray-500">
            Placed on {{ $order->created_at->format('M d, Y g:i A') }}
            by {{ $order->user?->name ?? 'Unknown' }} ({{ $order->user?->email }})
        </p>
    </div>

    <div class="flex items-center gap-3">
        <span class="px-3 py-1 rounded-full text-sm font-medium capitalize bg-gray-100 text-gray-800">
            {{ $order->status }}
        </span>

        @if (count($nextStatuses) > 0)
            <form method="POST" action="{{ route('admin.orders.status', $order) }}" class="flex items-center gap-2">
                @csrf
                @method('PATCH')
                <select name="status" class="border rounded px-2 py-1.5 text-sm capitalize bg-white">
                    <option value="{{ $order->status }}" selected disabled>{{ $order->status }}</option>
                    @foreach ($nextStatuses as $status)
                        <option value="{{ $status }}">{{ $status }}</option>
                    @endforeach
                </select>
                <button type="submit" class="px-3 py-1.5 rounded bg-gray-900 text-white text-sm hover:bg-gray-800">
                    Update status
                </button>
            </form>
        @endif
    </div>
</div>

@include('orders::partials.detail-sections', ['order' => $order, 'payment' => $payment, 'shipment' => $shipment])
@endsection
