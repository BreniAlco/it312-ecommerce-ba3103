@extends('layouts.app')

@section('title', 'Admin · Orders')

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <a href="{{ route('admin.dashboard') }}" class="text-sm text-gray-500 hover:underline">&larr; Back to dashboard</a>
        <h1 class="text-2xl font-bold mt-1">Manage orders</h1>
    </div>
</div>

@if ($orders->isEmpty())
    <p class="text-gray-600">No orders placed yet.</p>
@else
    <div class="bg-white rounded shadow overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="text-left border-b text-gray-600">
                <tr>
                    <th class="p-3">Order</th>
                    <th class="p-3">Customer</th>
                    <th class="p-3">Date</th>
                    <th class="p-3">Payment</th>
                    <th class="p-3 text-right">Total</th>
                    <th class="p-3">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($orders as $order)
                    @php
                        $nextStatuses = \App\Modules\Orders\Models\Order::TRANSITIONS[$order->status] ?? [];
                    @endphp
                    <tr class="border-b last:border-b-0">
                        <td class="p-3 font-medium">
                            <a href="{{ route('admin.orders.show', $order) }}" class="text-blue-600 hover:underline">
                                {{ $order->number() }}
                            </a>
                        </td>
                        <td class="p-3">
                            <div>{{ $order->user?->name ?? 'Unknown' }}</div>
                            <div class="text-xs text-gray-500">{{ $order->user?->email }}</div>
                        </td>
                        <td class="p-3">{{ $order->created_at->format('M d, Y') }}</td>
                        <td class="p-3 uppercase">{{ $order->payment_method }}</td>
                        <td class="p-3 text-right">₱{{ number_format((float) $order->total, 2) }}</td>
                        <td class="p-3">
                            @if (count($nextStatuses) > 0)
                                <form method="POST" action="{{ route('admin.orders.status', $order) }}" class="flex items-center gap-2">
                                    @csrf
                                    @method('PATCH')
                                    <select name="status" class="border rounded px-2 py-1 text-sm capitalize">
                                        <option value="{{ $order->status }}" selected disabled>{{ $order->status }}</option>
                                        @foreach ($nextStatuses as $status)
                                            <option value="{{ $status }}">{{ $status }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="px-3 py-1 rounded bg-gray-900 text-white text-xs hover:bg-gray-800">
                                        Update
                                    </button>
                                </form>
                            @else
                                <span class="px-2 py-1 rounded bg-gray-100 text-gray-700 text-xs capitalize">
                                    {{ $order->status }}
                                </span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-6">{{ $orders->links() }}</div>
@endif
@endsection
