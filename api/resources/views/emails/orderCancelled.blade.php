<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Storno objednávky</title>
    <style>
        body { font-family: Arial, sans-serif; color: #333; margin: 0; padding: 0; background: #f5f5f5; }
        .wrapper { max-width: 600px; margin: 30px auto; background: #fff; border-radius: 6px; overflow: hidden; }
        .header { background: #7f1d1d; color: #fff; padding: 28px 32px; }
        .header h1 { margin: 0; font-size: 22px; font-weight: 600; }
        .header p { margin: 6px 0 0; font-size: 14px; color: #fca5a5; }
        .body { padding: 32px; }
        .section-title { font-size: 12px; font-weight: 700; text-transform: uppercase; color: #888; letter-spacing: 0.5px; margin: 0 0 10px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        th { text-align: left; font-size: 12px; text-transform: uppercase; color: #888; padding: 6px 0; border-bottom: 1px solid #e5e5e5; }
        td { padding: 9px 0; font-size: 14px; border-bottom: 1px solid #f0f0f0; vertical-align: top; }
        .info-box { background: #fff7f7; border: 1px solid #fecaca; border-radius: 5px; padding: 16px 20px; margin-bottom: 24px; font-size: 14px; color: #7f1d1d; }
        .footer { background: #f9f9f9; padding: 20px 32px; font-size: 12px; color: #aaa; text-align: center; border-top: 1px solid #eee; }
    </style>
</head>
<body>
@php
    $shippedItems = $order->orderProducts->filter(fn($item) => $item->stockSum > 0);
    $cancelledItems = $order->orderProducts->filter(fn($item) => ($item->storno ?? 0) > 0 && $item->stockSum == 0);
@endphp
<div class="wrapper">
    <div class="header">
        <h1>Objednávka stornovaná</h1>
        @if($order->serial_number)
            <p>č. {{ $order->serial_number }}</p>
        @endif
    </div>

    <div class="body">
        <p style="font-size:15px; margin: 0 0 20px; line-height:1.6;">
            Dobrý deň, <strong>{{ $order->customer->company ?: $order->customer->name }}</strong>,<br>
            Vaša objednávka č. <strong>{{ $order->serial_number }}</strong> bola stornovaná.
            @if($shippedItems->count())
                Časť objednávky, ktorá už bola expedovaná, Vám zostáva — kontaktujte nás v prípade otázok.
            @else
                Žiadny tovar Vám nebol zaslaný.
            @endif
        </p>

        @if($cancelledItems->count())
        <p class="section-title">Stornované položky</p>
        <table>
            <thead>
                <tr>
                    <th>Tovar</th>
                    <th style="text-align:right">Množstvo</th>
                </tr>
            </thead>
            <tbody>
                @foreach($cancelledItems as $item)
                <tr>
                    <td>{{ $item->product->name ?? '—' }}</td>
                    <td style="text-align:right">{{ $item->storno }} {{ $item->product->unit_value ?? 'ks' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif

        @if($shippedItems->count())
        <div class="info-box">
            <strong style="display:block; margin-bottom:6px;">Už expedované položky</strong>
            Nasledujúce položky boli pred stornom odoslané a sú na ceste k Vám alebo už doručené:
            <ul style="margin:8px 0 0; padding-left:18px;">
                @foreach($shippedItems as $item)
                <li style="margin-bottom:4px;">{{ $item->product->name ?? '—' }} — {{ $item->stockSum }} {{ $item->product->unit_value ?? 'ks' }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <x-email.contact-block />
    </div>

    <div class="footer">
        Gajdoš Gabriel – Reprezent · Slatinská 14, 921 07 Bratislava · {{ now()->format('d.m.Y') }}
    </div>
</div>
</body>
</html>
