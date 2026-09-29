<!DOCTYPE html>
<html lang="sk"><head><meta charset="UTF-8"><title>Príprava objednávky</title></head>
<body style="font-family:Arial,sans-serif;color:#333;background:#f5f5f5;padding:24px">
<div style="max-width:600px;margin:auto;background:white;padding:32px">
<h1 style="color:#1e3a5f;font-size:24px">Vašu objednávku pripravujeme v sklade</h1>
<p>Objednávka č. {{ $order->serial_number }}</p>
<p>Dobrý deň, {{ $order->billing->company ?: $order->billing->name }},</p>
<p>začali sme pripravovať nižšie uvedené položky Vašej objednávky v sklade. Po odoslaní zásielky Vás budeme informovať samostatným e-mailom.</p>
<table style="width:100%;text-align:left"><thead><tr><th>Položka</th><th>Množstvo</th></tr></thead><tbody>
@foreach($shipping->prepared_items ?? [] as $item)
<tr><td style="padding:8px 0">{{ $item['name'] }}</td><td>{{ $item['quantity'] }} {{ $item['unit'] }}</td></tr>
@endforeach
</tbody></table>
<p>Tento e-mail potvrdzuje prípravu v sklade. Zásielka zatiaľ nebola odoslaná.</p>
<x-email.contact-block />
</div></body></html>
