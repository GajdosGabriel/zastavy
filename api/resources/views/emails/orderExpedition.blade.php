@php
    $isPartial = !$order->isFinished();

    // Položky odoslané v TEJTO zásielke
    $sentItems = $shipping
        ? $shipping->stocks->map(fn($s) => [
            'item'     => $s->orderProduct,
            'name'     => $s->orderProduct?->product_details?->name ?? '—',
            'quantity' => $s->quantity,
            'unit'     => $s->orderProduct?->product_details?->unit_value ?? 'ks',
          ])
        : collect();

    // Čo ešte ostáva poslať
    $remainingItems = $order->orderProducts->map(function ($item) {
        $remaining = max(0, $item->quantity - ($item->storno ?? 0) - $item->stockSum);
        return $remaining > 0 ? [
            'item'      => $item,
            'name'      => $item->product_details?->name ?? '—',
            'remaining' => $remaining,
            'unit'      => $item->product_details->unit_value ?? 'ks',
        ] : null;
    })->filter()->values();
@endphp
<x-email.layout :title="$isPartial ? 'Čiastočná expedícia' : 'Objednávka odoslaná'"
                :heading="$isPartial ? 'Čiastočná expedícia' : 'Objednávka odoslaná'"
                :subheading="$order->serial_number ? 'Objednávka č. '.$order->serial_number : null">
    <p class="lead">
        Dobrý deň, <strong>{{ $order->billing->company ?: $order->billing->name }}</strong>,<br>
        @if($isPartial)
            dnes sme Vám odoslali časť Vašej objednávky. Zostatok Vám pošleme hneď, ako bude tovar dostupný.
        @else
            Vaša objednávka bola dnes kompletne expedovaná a je na ceste k Vám.
        @endif
    </p>

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
                    <td>@if($item['item'])<x-email.order-item :item="$item['item']" />@else{{ $item['name'] }}@endif</td>
                    <td style="text-align:right; white-space:nowrap">{{ $item['quantity'] }} {{ $item['unit'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @else
    {{-- Bez údajov o zásielke ukážeme všetky položky objednávky --}}
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
                <td><x-email.order-item :item="$item" /></td>
                <td style="text-align:right; white-space:nowrap">{{ $item->quantity }} {{ $item->product_details->unit_value ?? 'ks' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    @if($isPartial && $remainingItems->count())
    <div class="pending-box">
        <div class="box-title">Zostatok – dorazí neskôr</div>
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
                    <td>@if($item['item'])<x-email.order-item :item="$item['item']" />@else{{ $item['name'] }}@endif</td>
                    <td style="text-align:right; white-space:nowrap">{{ $item['remaining'] }} {{ $item['unit'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    {{-- Adresa, na ktorú balík naozaj ide — nech ju zákazník skontroluje, kým je zásielka na ceste. --}}
    <x-email.delivery-address :order="$order" title="Zásielka ide na adresu" />

    @if($order->shipping_label)
    <div class="shipping-line">
        Spôsob doručenia: <strong>{{ $order->shipping_label }}</strong>
    </div>
    @endif

    <div class="button-row">
        <a href="{{ $order->publicUrl() }}" class="button">Zobraziť objednávku online</a>
    </div>

    <x-email.messages-link :order="$order" />
</x-email.layout>
