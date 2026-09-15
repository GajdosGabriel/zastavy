<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zmena adresy doručenia</title>
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
        .compare { width: 100%; border-collapse: collapse; margin-bottom: 28px; }
        .compare td { vertical-align: top; width: 50%; padding: 14px 16px; font-size: 14px; line-height: 1.5; }
        .compare .old { background: #f7f7f7; color: #888; text-decoration: line-through; }
        .compare .new { background: #f0fdf4; color: #166534; border-left: 3px solid #16a34a; }
        .compare th { text-align: left; font-size: 12px; text-transform: uppercase; color: #888; padding: 0 16px 6px; }
        .footer { background: #f9f9f9; padding: 20px 32px; font-size: 12px; color: #aaa; text-align: center; border-top: 1px solid #eee; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="header">
        <h1>Zmena adresy doručenia</h1>
        @if($order->serial_number)
            <p>Objednávka č. {{ $order->serial_number }}</p>
        @endif
    </div>

    <div class="body">
        <x-email.customer :customer="$order->billing" />

        <p style="font-size:15px; margin:0 0 20px; line-height:1.6;">
            Adresu doručenia objednávky sme upravili
            @if($order->delivery_changed_by) — zmenu zadal <strong>{{ $order->delivery_changed_by }}</strong>@endif.
            Tovar pošleme na novú adresu.
        </p>

        <table class="compare">
            <thead>
                <tr>
                    <th>Pôvodná adresa</th>
                    <th>Nová adresa</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="old">
                        {{ collect([$previous['company'], $previous['name'], $previous['street'], trim(($previous['postcode'] ?? '').' '.($previous['city'] ?? ''))])->filter()->implode(', ') }}
                    </td>
                    <td class="new">
                        {{ collect([$current['company'], $current['name'], $current['street'], trim(($current['postcode'] ?? '').' '.($current['city'] ?? ''))])->filter()->implode(', ') }}
                        @if($current['phone'])<br><span style="font-size:12px;">Tel.: {{ $current['phone'] }}</span>@endif
                        @if($current['note'])<br><span style="font-size:12px;">{{ $current['note'] }}</span>@endif
                    </td>
                </tr>
            </tbody>
        </table>

        <p style="font-size:13px; color:#666; margin:0 0 24px;">
            Ak ste zmenu nezadali vy, ozvite sa nám čo najskôr na obchod&#64;zastavy-vlajky.sk — adresu vrátime späť.
        </p>

        <div class="info-block" style="text-align:center;">
            <a href="{{ $order->publicUrl() }}"
               style="display:inline-block; background:#1e3a5f; color:#fff; text-decoration:none; padding:12px 28px; border-radius:5px; font-size:14px; font-weight:600;">
                Zobraziť objednávku online
            </a>
        </div>

        <x-email.contact-block />
    </div>

    <div class="footer">
        Gajdoš Gabriel – Reprezent · Slatinská 14, 921 07 Bratislava · {{ now()->format('d.m.Y') }}
    </div>
</div>
</body>
</html>
