@props(['order', 'title' => 'Objednané položky'])
{{-- Položky objednávky s cenami a rekapituláciou (doprava, poplatok, zľavy, spolu).
     Zdieľa ho potvrdenie aj zmena objednávky, aby súčty vyzerali všade rovnako. --}}
@php
    $money = fn ($value) => number_format((float) $value, 2, ',', ' ') . ' €';
    $hasPrices = (bool) $order->orderProducts->first()?->price;
    $shipping = (float) ($order->shipping_price ?? 0);
    $fee = (float) ($order->payment_fee ?? 0);
    $discount = (float) ($order->discount_amount ?? 0);
    $adjustment = (float) $order->adjustmentAmount();
@endphp
<p class="section-title">{{ $title }}</p>
<table>
    <thead>
        <tr>
            <th>Tovar</th>
            <th style="text-align:right; padding-left:10px;">Množstvo</th>
            @if($hasPrices)
                <th style="text-align:right; padding-left:10px;">Cena/ks</th>
                <th style="text-align:right; padding-left:10px;">Spolu</th>
            @endif
        </tr>
    </thead>
    <tbody>
        @foreach($order->orderProducts as $item)
        <tr>
            <td><x-email.order-item :item="$item" /></td>
            <td style="text-align:right; white-space:nowrap; padding-left:10px;">{{ $item->quantity }} {{ $item->product_details->unit_value ?? 'ks' }}@if($item->storno > 0)<br><span style="color:#b91c1c; font-size:12px;">z toho storno {{ $item->storno }}</span>@endif</td>
            @if($hasPrices)
                <td style="text-align:right; white-space:nowrap; padding-left:10px;">{{ $money($item->price) }}</td>
                <td style="text-align:right; white-space:nowrap; padding-left:10px;">{{ $money($item->total) }}</td>
            @endif
        </tr>
        @endforeach

        @if($hasPrices)
            @if($shipping > 0)
            <tr>
                <td colspan="3" style="color:#555;">Poštovné{{ $order->shipping_label ? ' ('.$order->shipping_label.')' : '' }}</td>
                <td style="text-align:right; color:#555; white-space:nowrap">{{ $money($shipping) }}</td>
            </tr>
            @elseif($order->shipping_label)
            <tr>
                <td colspan="3" style="color:#555;">Poštovné ({{ $order->shipping_label }})</td>
                <td style="text-align:right; color:#15803d;">Zdarma</td>
            </tr>
            @endif
            @if($fee > 0)
            <tr>
                <td colspan="3" style="color:#555;">{{ $order->payment_label ?? 'Poplatok za platbu' }}</td>
                <td style="text-align:right; color:#555; white-space:nowrap">{{ $money($fee) }}</td>
            </tr>
            @endif
            @if($discount > 0)
            <tr>
                <td colspan="3" style="color:#15803d;">Zľavový kupón</td>
                <td style="text-align:right; color:#15803d; white-space:nowrap">−{{ $money($discount) }}</td>
            </tr>
            @endif
            @if($adjustment != 0)
            <tr>
                <td colspan="3">{{ ($order->price_adjustment['label'] ?? null) ?: ($adjustment < 0 ? 'Zľava' : 'Prirážka') }}</td>
                <td style="text-align:right; white-space:nowrap; padding-left:10px;">{{ $money($adjustment) }}</td>
            </tr>
            @endif
            <tr class="total-row">
                <td colspan="3">Celková suma</td>
                <td style="text-align:right; white-space:nowrap; padding-left:10px;">{{ $money($order->grandTotal()) }}</td>
            </tr>
        @endif
    </tbody>
</table>
