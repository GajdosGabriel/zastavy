<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Potvrdenie objednávky</title>
    <style>
        body { font-family: Arial, sans-serif; color: #333; margin: 0; padding: 0; background: #f5f5f5; }
        .wrapper { max-width: 600px; margin: 30px auto; background: #fff; border-radius: 6px; overflow: hidden; }
        .header { background: #1e3a5f; color: #fff; padding: 28px 32px; }
        .header h1 { margin: 0; font-size: 22px; font-weight: 600; }
        .header p { margin: 6px 0 0; font-size: 14px; color: #aaa; }
        .body { padding: 32px; }
        .section-title { font-size: 13px; font-weight: 700; text-transform: uppercase; color: #888; letter-spacing: 0.5px; margin: 0 0 12px; }
        .info-block { margin-bottom: 28px; }
        .info-block p { margin: 4px 0; font-size: 14px; }
        .meta-line { font-size: 13px; color: #666; margin-bottom: 20px; }
        .meta-line span { color: #333; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 28px; }
        th { text-align: left; font-size: 12px; text-transform: uppercase; color: #888; padding: 6px 0; border-bottom: 1px solid #e5e5e5; }
        td { padding: 10px 0; font-size: 14px; border-bottom: 1px solid #f0f0f0; vertical-align: top; }
        .total-row td { font-weight: 700; font-size: 15px; border-bottom: none; padding-top: 14px; }
        .footer { background: #f9f9f9; padding: 20px 32px; font-size: 12px; color: #aaa; text-align: center; border-top: 1px solid #eee; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="header">
        <h1>Potvrdenie objednávky</h1>
        @if($order->serial_number)
            <p>Číslo objednávky: {{ $order->serial_number }}</p>
        @endif
    </div>

    <div class="body">
        <div class="info-block">
            <p class="section-title">Zákazník</p>
            @if($order->customer->company)
                <p><strong>{{ $order->customer->company }}</strong></p>
            @endif
            <p>{{ $order->customer->name }}</p>
            @if($order->customer->email)
                <p>{{ $order->customer->email }}</p>
            @endif
            @if($order->customer->phone)
                <p>{{ $order->customer->phone }}</p>
            @endif
        </div>

        @if($order->customer->street || $order->customer->city)
        <div class="info-block">
            <p class="section-title">Doručovacia adresa</p>
            @if($order->customer->street)
                <p>{{ $order->customer->street }}</p>
            @endif
            <p>{{ $order->customer->postcode }} {{ $order->customer->city }}</p>
        </div>
        @endif

        @if($order->shippingMethod || $order->paymentMethod)
        <div class="meta-line">
            @if($order->shippingMethod)Doprava: <span>{{ $order->shippingMethod->name }}</span>@endif
            @if($order->shippingMethod && $order->paymentMethod) &nbsp;·&nbsp; @endif
            @if($order->paymentMethod)Platba: <span>{{ $order->paymentMethod->name }}</span>@endif
        </div>
        @endif

        <p class="section-title">Objednané položky</p>
        <table>
            <thead>
                <tr>
                    <th>Tovar</th>
                    <th style="text-align:right">Množstvo</th>
                    @if($order->orderProducts->first()?->price)
                        <th style="text-align:right">Cena/ks</th>
                        <th style="text-align:right">Spolu</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @foreach($order->orderProducts as $item)
                <tr>
                    <td>{{ $item->product->name ?? '—' }}</td>
                    <td style="text-align:right">{{ $item->quantity }} ks</td>
                    @if($order->orderProducts->first()?->price)
                        <td style="text-align:right">{{ number_format($item->price, 2, ',', ' ') }} €</td>
                        <td style="text-align:right">{{ number_format($item->total, 2, ',', ' ') }} €</td>
                    @endif
                </tr>
                @endforeach
                @if($order->orderProducts->first()?->price)
                @php
                    $subtotal  = $order->orderProducts->sum('total');
                    $shipping  = (float) ($order->shipping_price ?? 0);
                    $fee       = (float) ($order->payment_fee ?? 0);
                    $discount  = (float) ($order->discount_amount ?? 0);
                    $grandTotal = $subtotal + $shipping + $fee - $discount;
                @endphp
                @if($shipping > 0)
                <tr>
                    <td colspan="3" style="color:#666;">Poštovné{{ $order->shippingMethod ? ' ('.$order->shippingMethod->name.')' : '' }}</td>
                    <td style="text-align:right;color:#666;">{{ number_format($shipping, 2, ',', ' ') }} €</td>
                </tr>
                @elseif($order->shippingMethod)
                <tr>
                    <td colspan="3" style="color:#666;">Poštovné ({{ $order->shippingMethod->name }})</td>
                    <td style="text-align:right;color:#28a745;">Zdarma</td>
                </tr>
                @endif
                @if($fee > 0)
                <tr>
                    <td colspan="3" style="color:#666;">{{ $order->paymentMethod?->name ?? 'Poplatok za platbu' }}</td>
                    <td style="text-align:right;color:#666;">{{ number_format($fee, 2, ',', ' ') }} €</td>
                </tr>
                @endif
                @if($discount > 0)
                <tr>
                    <td colspan="3" style="color:#28a745;">Zľava</td>
                    <td style="text-align:right;color:#28a745;">−{{ number_format($discount, 2, ',', ' ') }} €</td>
                </tr>
                @endif
                <tr class="total-row">
                    <td colspan="3">Celková suma</td>
                    <td style="text-align:right">{{ number_format($grandTotal, 2, ',', ' ') }} €</td>
                </tr>
                @endif
            </tbody>
        </table>

        @if(!empty(trim($order->note ?? '')))
        <div class="info-block">
            <p class="section-title">Poznámka</p>
            <p style="font-size:14px;">{{ $order->note }}</p>
        </div>
        @endif

        <x-email.contact-block />
    </div>

    <div class="footer">
        Gajdoš Gabriel – Reprezent · Slatinská 14, 921 07 Bratislava · {{ $order->created_at->format('d.m.Y') }}
    </div>
</div>
</body>
</html>
