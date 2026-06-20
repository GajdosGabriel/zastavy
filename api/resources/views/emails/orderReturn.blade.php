<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vrátenie tovaru</title>
    <style>
        body { font-family: Arial, sans-serif; color: #333; margin: 0; padding: 0; background: #f5f5f5; }
        .wrapper { max-width: 600px; margin: 30px auto; background: #fff; border-radius: 6px; overflow: hidden; }
        .header { background: #92400e; color: #fff; padding: 28px 32px; }
        .header h1 { margin: 0; font-size: 22px; font-weight: 600; }
        .header p { margin: 6px 0 0; font-size: 14px; color: #fde68a; }
        .body { padding: 32px; }
        .section-title { font-size: 12px; font-weight: 700; text-transform: uppercase; color: #888; letter-spacing: 0.5px; margin: 0 0 10px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        th { text-align: left; font-size: 12px; text-transform: uppercase; color: #888; padding: 6px 0; border-bottom: 1px solid #e5e5e5; }
        td { padding: 9px 0; font-size: 14px; border-bottom: 1px solid #f0f0f0; vertical-align: top; }
        .info-box { border-radius: 5px; padding: 16px 20px; margin-bottom: 24px; font-size: 14px; }
        .info-box.orange { background: #fffbeb; border: 1px solid #fcd34d; color: #78350f; }
        .info-box.blue { background: #eff6ff; border: 1px solid #93c5fd; color: #1e40af; }
        .footer { background: #f9f9f9; padding: 20px 32px; font-size: 12px; color: #aaa; text-align: center; border-top: 1px solid #eee; }
    </style>
</head>
<body>
@php
    $messages = [
        'not_accepted' => [
            'title' => 'Vrátená zásielka',
            'body'  => 'Vaša zásielka sa nám vrátila ako neprevzatá. Kontaktujte nás prosím, aby sme dohodli ďalší postup — opätovné doručenie alebo storno objednávky.',
            'action' => null,
        ],
        'damaged' => [
            'title' => 'Vrátenie poškodeného tovaru',
            'body'  => 'Prijali sme od Vás vrátenie poškodeného tovaru. Vašu reklamáciu preveríme a budeme Vás čo najskôr kontaktovať.',
            'action' => null,
        ],
        'wrong_item' => [
            'title' => 'Vrátenie tovaru — nesprávna položka',
            'body'  => 'Prijali sme od Vás vrátenie tovaru. Ospravedlňujeme sa za chybu — správny tovar Vám zašleme v najbližšom možnom termíne.',
            'action' => 'Správny tovar Vám expedujeme čo najskôr.',
        ],
        'other' => [
            'title' => 'Vrátenie tovaru',
            'body'  => 'Prijali sme vrátenie tovaru z Vašej objednávky. Budeme Vás kontaktovať ohľadom ďalšieho postupu.',
            'action' => null,
        ],
    ];
    $msg = $messages[$orderReturn->reason] ?? $messages['other'];
@endphp
<div class="wrapper">
    <div class="header">
        <h1>{{ $msg['title'] }}</h1>
        <p>Objednávka č. {{ $order->serial_number }}</p>
    </div>

    <div class="body">
        <x-email.customer :customer="$order->customer" />

        <p style="font-size:15px; margin: 0 0 20px; line-height:1.6;">
            Dobrý deň, <strong>{{ $order->customer->company ?: $order->customer->name }}</strong>,<br>
            {{ $msg['body'] }}
        </p>

        @if($msg['action'])
        <div class="info-box blue" style="margin-bottom:20px;">
            {{ $msg['action'] }}
        </div>
        @endif

        @if($orderReturn->note)
        <div class="info-box orange" style="margin-bottom:20px;">
            <strong style="display:block; margin-bottom:4px;">Poznámka</strong>
            {{ $orderReturn->note }}
        </div>
        @endif

        <p class="section-title">Vrátené položky</p>
        <table>
            <thead>
                <tr>
                    <th>Tovar</th>
                    <th style="text-align:right">Množstvo</th>
                </tr>
            </thead>
            <tbody>
                @foreach($orderReturn->items as $item)
                <tr>
                    <td>{{ $item->orderProduct?->product?->name ?? '—' }}</td>
                    <td style="text-align:right">
                        {{ $item->quantity }} {{ $item->orderProduct?->product?->unit_value ?? 'ks' }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <x-email.contact-block />
    </div>

    <div class="footer">
        Gajdoš Gabriel – Reprezent · Slatinská 14, 921 07 Bratislava · {{ now()->format('d.m.Y') }}
    </div>
</div>
</body>
</html>
