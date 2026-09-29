<!DOCTYPE html>
<html lang="sk">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Potvrdenie odoslania</title></head>
<body style="font-family:Arial,sans-serif;color:#333;background:#f5f5f5;padding:24px">
<div style="max-width:600px;margin:auto;background:white;padding:32px">
<h1 style="color:#1e3a5f;font-size:24px">Potvrdenie odoslania</h1>
<p>Objednávka č. {{ $order->serial_number }}</p>
<p>Dobrý deň, {{ $order->billing->company ?: $order->billing->name }},</p>
<p>nižšie uvedené položky Vašej objednávky sme vybavili a zásielku odoslali.</p>
<table style="width:100%;text-align:left"><thead><tr><th>Položka</th><th>Množstvo</th></tr></thead><tbody>
@foreach($shipping->prepared_items ?? [] as $item)
<tr><td style="padding:8px 0">{{ $item['name'] }}</td><td>{{ $item['quantity'] }} {{ $item['unit'] }}</td></tr>
@endforeach
</tbody></table>
@php
    $selection = collect($shipping->prepared_items ?? [])->keyBy('order_product_id');
    $remaining = $order->orderProducts->map(function ($item) use ($shipping, $selection) {
        $dispatching = $shipping->dispatched_at ? 0 : ($selection->get($item->id)['quantity'] ?? 0);
        return ['name' => $item->product_details->name, 'unit' => $item->product_details->unit_value,
            'quantity' => max(0, $item->quantity - $item->storno - $item->stockSum - $dispatching)];
    })->filter(fn ($item) => $item['quantity'] > 0);
@endphp
@if($remaining->isNotEmpty())
<h2 style="font-size:18px">Zostáva vybaviť</h2>
@foreach($remaining as $item)<p>{{ $item['name'] }} — {{ $item['quantity'] }} {{ $item['unit'] }}</p>@endforeach
<p>O odoslaní ďalšej časti Vás budeme informovať.</p>
@endif
<x-email.delivery-address :order="$order" title="Zásielka ide na adresu" />
@if($order->shipping_label)<p>Spôsob doručenia: <strong>{{ $order->shipping_label }}</strong></p>@endif
<p><a href="{{ $order->publicUrl() }}">Zobraziť objednávku</a></p>
<x-email.contact-block />
</div></body></html>
