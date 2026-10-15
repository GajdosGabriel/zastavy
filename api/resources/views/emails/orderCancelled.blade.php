@php
    $shippedItems = $order->orderProducts->filter(fn($item) => $item->stockSum > 0);
    $cancelledItems = $order->orderProducts->filter(fn($item) => ($item->storno ?? 0) > 0 && $item->stockSum == 0);
@endphp
<x-email.layout title="Storno objednávky" heading="Objednávka stornovaná" accent="#991b1b"
                :subheading="$order->serial_number ? 'Objednávka č. '.$order->serial_number : null">
    <p class="lead">
        Dobrý deň, <strong>{{ $order->billing->company ?: $order->billing->name }}</strong>,<br>
        Vaša objednávka č. <strong>{{ $order->serial_number }}</strong> bola stornovaná.
        @if($shippedItems->count())
            Časť objednávky, ktorá už bola expedovaná, Vám zostáva — v prípade otázok nás kontaktujte.
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
                <td><x-email.order-item :item="$item" /></td>
                <td style="text-align:right; white-space:nowrap">{{ $item->storno }} {{ $item->product_details->unit_value ?? 'ks' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    @if($shippedItems->count())
    <div class="note-box">
        <strong style="display:block; margin-bottom:6px;">Už expedované položky</strong>
        Nasledujúce položky boli pred stornom odoslané a sú na ceste k Vám alebo už doručené:
        <ul style="margin:8px 0 0; padding-left:18px;">
            @foreach($shippedItems as $item)
            <li style="margin-bottom:4px;">{{ $item->product_details->name ?? '—' }}@if($item->variant_name) ({{ $item->variant_name }})@endif — {{ $item->stockSum }} {{ $item->product_details->unit_value ?? 'ks' }}</li>
            @endforeach
        </ul>
    </div>
    @endif
</x-email.layout>
