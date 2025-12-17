<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice #{{ $order->id }}</title>
    <style>
        body { font-family: sans-serif; }
        h1 { color: #0D47A1; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f4f4f4; }
        .total { font-weight: bold; }
    </style>
</head>
<body>
    <h1>Invoice - Order #{{ $order->id }}</h1>
    <p>Date: {{ $order->created_at->toFormattedDateString() }}</p>
    <p>User: {{ $order->user->name }} ({{ $order->user->email }})</p>
    <p>Restaurant: {{ $order->restaurant->name }}</p>

    <table>
        <thead>
            <tr>
                <th>Item</th>
                <th>Qty</th>
                <th>Price (₹)</th>
                <th>Subtotal (₹)</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($order->items as $item)
                <tr>
                    <td>{{ $item->menuItem->name }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td>{{ number_format($item->price / 100, 2) }}</td>
                    <td>{{ number_format(($item->price * $item->quantity) / 100, 2) }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td colspan="3">Total</td>
                <td>₹{{ number_format($order->total / 100, 2) }}</td>
            </tr>
        </tbody>
    </table>
</body>
</html>
