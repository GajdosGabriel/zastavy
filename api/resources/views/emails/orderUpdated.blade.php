<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zmena objednávky</title>
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
        .change-block { background: #f0f4ff; border-left: 4px solid #1e3a5f; border-radius: 4px; padding: 16px 20px; margin: 0 0 28px; }
        .change-row { display: flex; gap: 8px; align-items: center; font-size: 14px; margin: 6px 0; }
        .change-label { color: #555; min-width: 150px; }
        .change-old { color: #e53e3e; text-decoration: line-through; }
        .change-arrow { color: #888; }
        .change-new { color: #2f855a; font-weight: 600; }
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
        <h1>Zmena objednávky</h1>
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
        </div>

        @if(!empty($changes))
        <div class="info-block">
            <p class="section-title">Čo sa zmenilo</p>
            <div class="change-block">
                @foreach($changes as $change)
                <div class="change-row">
                    <span class="change-label">{{ $change['label'] }}:</span>
                    <span class="change-old">{{ $change['old'] ?? '—' }}</span>
                    <span class="change-arrow">→</span>
                    <span class="change-new">{{ $change['new'] ?? '—' }}</span>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        <p class="section-title">Objednané položky</p>
        <table>
            <thead>
                <tr>
                    <th>Produkt</th>
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
                <tr class="total-row">
                    <td colspan="3">Celková suma</td>
                    <td style="text-align:right">{{ number_format($order->orderProducts->sum('total'), 2, ',', ' ') }} €</td>
                </tr>
                @endif
            </tbody>
        </table>

        <x-email.contact-block />
    </div>

    <div class="footer">
        Táto správa bola vygenerovaná automaticky · {{ now()->format('d.m.Y') }}
    </div>
</div>
</body>
</html>
