@php
    // Riadky dodacieho listu doplníme o položku objednávky, aby mali náhľad
    // a variant rovnako ako v potvrdení objednávky.
    $prepared = collect($shipping->prepared_items ?? [])
        ->map(fn ($row) => $row + ['item' => $order->orderProducts->firstWhere('id', $row['order_product_id'] ?? null)]);
@endphp
<x-email.layout title="Príprava objednávky" heading="Vašu objednávku pripravujeme v sklade"
                :subheading="$order->serial_number ? 'Objednávka č. '.$order->serial_number : null">
    <p class="lead">
        Dobrý deň, <strong>{{ $order->billing->company ?: $order->billing->name }}</strong>,<br>
        začali sme pripravovať nižšie uvedené položky Vašej objednávky v sklade.
    </p>

    <p class="section-title">Pripravované položky</p>
    <table>
        <thead>
            <tr>
                <th>Tovar</th>
                <th style="text-align:right">Množstvo</th>
            </tr>
        </thead>
        <tbody>
            @foreach($prepared as $item)
            <tr>
                <td>@if($item['item'])<x-email.order-item :item="$item['item']" />@else{{ $item['name'] }}@endif</td>
                <td style="text-align:right; white-space:nowrap">{{ $item['quantity'] }} {{ $item['unit'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="note-box">
        Tento e-mail potvrdzuje prípravu v sklade. Zásielka zatiaľ nebola odoslaná — po jej odoslaní Vás budeme informovať samostatným e-mailom.
    </div>
</x-email.layout>
