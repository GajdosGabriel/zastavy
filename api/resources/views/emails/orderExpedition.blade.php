<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expedícia objednávky</title>
    <style>
        body { font-family: Arial, sans-serif; color: #333; margin: 0; padding: 0; background: #f5f5f5; }
        .wrapper { max-width: 600px; margin: 30px auto; background: #fff; border-radius: 6px; overflow: hidden; }
        .header { background: #1e3a5f; color: #fff; padding: 28px 32px; }
        .header h1 { margin: 0; font-size: 22px; font-weight: 600; }
        .header p { margin: 6px 0 0; font-size: 14px; color: #aaa; }
        .body { padding: 32px; }
        .section-title { font-size: 12px; font-weight: 700; text-transform: uppercase; color: #888; letter-spacing: 0.5px; margin: 0 0 10px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        th { text-align: left; font-size: 12px; text-transform: uppercase; color: #888; padding: 6px 0; border-bottom: 1px solid #e5e5e5; }
        td { padding: 9px 0; font-size: 14px; border-bottom: 1px solid #f0f0f0; vertical-align: top; }
        .sent-box { background: #f0fdf4; border: 1px solid #86efac; border-radius: 5px; padding: 16px 20px; margin-bottom: 24px; }
        .sent-box .box-title { font-size: 13px; font-weight: 700; color: #166534; margin-bottom: 10px; }
        .sent-box table { margin-bottom: 0; }
        .sent-box th { color: #15803d; border-bottom-color: #86efac; }
        .sent-box td { font-size: 14px; color: #166534; border-bottom-color: #dcfce7; }
        .pending-box { background: #fffbeb; border: 1px solid #fcd34d; border-radius: 5px; padding: 16px 20px; margin-bottom: 24px; }
        .pending-box .box-title { font-size: 13px; font-weight: 700; color: #92400e; margin-bottom: 10px; }
        .pending-box table { margin-bottom: 0; }
        .pending-box th { color: #a16207; border-bottom-color: #fcd34d; }
        .pending-box td { font-size: 13px; color: #78350f; border-bottom-color: #fde68a; }
        .shipping-line { font-size: 14px; color: #555; margin-bottom: 24px; }
        .shipping-line strong { color: #1e3a5f; }
        .footer { background: #f9f9f9; padding: 20px 32px; font-size: 12px; color: #aaa; text-align: center; border-top: 1px solid #eee; }
    </style>
</head>
<body>
@php
    $isPartial = !$order->isFinished();

    // Items sent in THIS shipment
    $sentItems = $shipping
        ? $shipping->stocks->map(fn($s) => [
            'name'     => $s->orderProduct?->product?->name ?? '—',
            'quantity' => $s->quantity,
            'unit'     => $s->orderProduct?->unit_value ?? 'ks',
          ])
        : collect();

    // Remaining items still to be sent
    $remainingItems = $order->orderProducts->map(function ($item) {
        $remaining = max(0, $item->quantity - ($item->storno ?? 0) - $item->stockSum);
        return $remaining > 0 ? [
            'name'      => $item->product?->name ?? '—',
            'remaining' => $remaining,
            'unit'      => $item->unit_value ?? 'ks',
        ] : null;
    })->filter()->values();
@endphp
<div class="wrapper">
    <div class="header">
        <h1>{{ $isPartial ? 'Čiastočná expedícia' : 'Objednávka odoslaná' }}</h1>
        @if($order->serial_number)
            <p>Objednávka č. {{ $order->serial_number }}</p>
        @endif
    </div>

    <div class="body">
        <p style="font-size:15px; margin: 0 0 24px; line-height:1.6;">
            Dobrý deň, <strong>{{ $order->customer->company ?: $order->customer->name }}</strong>,<br>
            @if($isPartial)
                dnes sme Vám odoslali časť Vašej objednávky. Zostatok Vám pošleme hneď, ako bude tovar dostupný.
            @else
                Vaša objednávka bola dnes kompletne expedovaná a je na ceste k Vám.
            @endif
        </p>

        @if($order->shippingMethod)
        <div class="shipping-line">
            Spôsob doručenia: <strong>{{ $order->shippingMethod->name }}</strong>
        </div>
        @endif

        {{-- Sent now --}}
        @if($sentItems->count())
        <div class="sent-box">
            <div class="box-title">{{ $isPartial ? 'Dnes Vám posielame' : 'Odoslaný tovar' }}</div>
            <table>
                <thead>
                    <tr>
                        <th>Tovar</th>
                        <th style="text-align:right">Množstvo</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($sentItems as $item)
                    <tr>
                        <td>{{ $item['name'] }}</td>
                        <td style="text-align:right">{{ $item['quantity'] }} {{ $item['unit'] }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        {{-- Fallback: no shipping data, show all order products --}}
        <p class="section-title">Objednané položky</p>
        <table>
            <thead>
                <tr>
                    <th>Tovar</th>
                    <th style="text-align:right">Množstvo</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order->orderProducts as $item)
                <tr>
                    <td>{{ $item->product?->name ?? '—' }}</td>
                    <td style="text-align:right">{{ $item->quantity }} {{ $item->unit_value ?? 'ks' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif

        {{-- Remaining items --}}
        @if($isPartial && $remainingItems->count())
        <div class="pending-box">
            <div class="box-title">Zostatok – dorazia neskôr</div>
            <table>
                <thead>
                    <tr>
                        <th>Tovar</th>
                        <th style="text-align:right">Zostatok</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($remainingItems as $item)
                    <tr>
                        <td>{{ $item['name'] }}</td>
                        <td style="text-align:right">{{ $item['remaining'] }} {{ $item['unit'] }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
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
