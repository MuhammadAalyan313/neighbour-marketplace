@php
    $isPayment = $notificationType === 'order_payment_confirmed';
    $isUpdate = $notificationType === 'order_status_updated';
    $heading = $isPayment ? 'Payment confirmed' : ($isUpdate ? 'Your order has been updated' : 'Your order has been received');
    $intro = $isPayment
        ? 'The seller has confirmed payment for your order.'
        : ($isUpdate ? 'Here is the latest status of your order.' : 'Thanks for shopping locally. Your order details are below.');
    $orderStatus = ucfirst(str_replace('_', ' ', (string) ($receipt['order_status'] ?? 'pending')));
    $paymentStatus = ucfirst(str_replace('_', ' ', (string) ($receipt['payment_status'] ?? 'unpaid')));
    $paymentMethod = ucwords(str_replace('_', ' ', (string) ($receipt['payment_method'] ?? 'Not specified')));
    $fulfillment = ucwords(str_replace('_', ' ', (string) ($receipt['fulfillment_type'] ?? 'Not specified')));
    $money = static function ($value): string {
        return 'Rs. ' . number_format((float) ($value ?? 0), 2);
    };
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $heading }} - Neighbour Marketplace</title>
</head>
<body style="margin:0;padding:0;background:#f3f6f5;color:#1f2937;font-family:Arial,Helvetica,sans-serif;">
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#f3f6f5;">
    <tr><td align="center" style="padding:30px 12px;">
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" style="width:100%;max-width:600px;background:#ffffff;border:1px solid #e5e7eb;border-radius:12px;">
            <tr><td style="background:#134e40;padding:28px 30px;text-align:center;">
                <div style="color:#ffffff;font-size:24px;font-weight:700;letter-spacing:.3px;">Neighbour Marketplace</div>
                <div style="color:#cfede4;font-size:12px;padding-top:8px;">YOUR NEIGHBOURHOOD. YOUR MARKETPLACE.</div>
            </td></tr>
            <tr><td style="padding:30px;">
                <h1 style="margin:0 0 12px;color:#123f35;font-size:25px;line-height:1.3;">{{ $heading }}</h1>
                <p style="margin:0 0 20px;font-size:15px;line-height:1.6;">Hello {{ $receipt['buyer_name'] ?? 'Customer' }},<br>{{ $intro }}</p>

                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#f7faf9;border:1px solid #e2ece8;border-radius:8px;">
                    <tr>
                        <td style="padding:15px 17px;vertical-align:top;">
                            <div style="color:#64748b;font-size:12px;">ORDER NUMBER</div>
                            <div style="font-size:16px;font-weight:700;padding-top:5px;">{{ $receipt['order_number'] ?? '—' }}</div>
                        </td>
                        <td style="padding:15px 17px;vertical-align:top;text-align:right;">
                            <div style="color:#64748b;font-size:12px;">PAYMENT</div>
                            <div style="font-size:15px;font-weight:700;padding-top:5px;color:{{ ($receipt['payment_status'] ?? 'unpaid') === 'paid' ? '#166534' : '#92400e' }};">{{ $paymentStatus }}</div>
                        </td>
                    </tr>
                </table>

                <h2 style="font-size:17px;margin:26px 0 10px;color:#123f35;">{{ $receipt['store_name'] ?? 'Your Seller' }}</h2>
                <div style="font-size:13px;color:#64748b;margin-bottom:12px;">Items in this store's order</div>
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse:collapse;">
                    <tr style="background:#f7faf9;">
                        <th align="left" style="padding:11px 8px;font-size:12px;color:#475569;border-bottom:1px solid #e5e7eb;">ITEM</th>
                        <th align="center" style="padding:11px 8px;font-size:12px;color:#475569;border-bottom:1px solid #e5e7eb;">QTY</th>
                        <th align="right" style="padding:11px 8px;font-size:12px;color:#475569;border-bottom:1px solid #e5e7eb;">AMOUNT</th>
                    </tr>
                    @foreach (($receipt['items'] ?? []) as $item)
                        <tr>
                            <td style="padding:13px 8px;border-bottom:1px solid #eef2f1;font-size:14px;">
                                <strong>{{ $item['product_title'] ?? 'Product' }}</strong><br>
                                <span style="color:#64748b;font-size:12px;">{{ $money($item['unit_price'] ?? 0) }} each</span>
                            </td>
                            <td align="center" style="padding:13px 8px;border-bottom:1px solid #eef2f1;font-size:14px;">{{ $item['quantity'] ?? 1 }}</td>
                            <td align="right" style="padding:13px 8px;border-bottom:1px solid #eef2f1;font-size:14px;">{{ $money($item['subtotal'] ?? 0) }}</td>
                        </tr>
                    @endforeach
                </table>

                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-top:16px;font-size:14px;">
                    @foreach ([
                        'Subtotal' => 'subtotal',
                        'Discount' => 'discount_amount',
                        'Delivery fee' => 'delivery_fee',
                        'Platform fee' => 'platform_fee',
                    ] as $label => $key)
                        <tr><td style="padding:6px 0;color:#64748b;">{{ $label }}</td><td align="right" style="padding:6px 0;">{{ $money($receipt[$key] ?? 0) }}</td></tr>
                    @endforeach
                    <tr><td style="border-top:2px solid #dce9e3;padding:15px 0;font-size:17px;font-weight:700;color:#123f35;">Order total</td><td align="right" style="border-top:2px solid #dce9e3;padding:15px 0;font-size:19px;font-weight:700;color:#123f35;">{{ $money($receipt['total_amount'] ?? 0) }}</td></tr>
                </table>

                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-top:12px;background:#f7faf9;border-radius:8px;">
                    <tr><td style="padding:14px;font-size:13px;line-height:1.8;">
                        <strong>Order status:</strong> {{ $orderStatus }}<br>
                        <strong>Payment method:</strong> {{ $paymentMethod }}<br>
                        <strong>Fulfilment:</strong> {{ $fulfillment }}
                    </td></tr>
                </table>

                <p style="margin:22px 0 0;color:#64748b;font-size:12px;line-height:1.6;">
                    @if (($receipt['payment_status'] ?? 'unpaid') === 'paid')
                        Payment has been marked as confirmed by the seller.
                    @else
                        This email acknowledges your order. It is not proof of payment; payment is still pending confirmation.
                    @endif
                </p>
                <p style="margin:18px 0 0;font-size:14px;">Thank you for supporting your neighbours!</p>
            </td></tr>
            <tr><td style="padding:22px 30px;text-align:center;border-top:1px solid #e5e7eb;color:#64748b;font-size:12px;line-height:1.6;">
                Neighbour Marketplace<br>This is an automated transactional message about your order.
            </td></tr>
        </table>
    </td></tr>
</table>
</body>
</html>
