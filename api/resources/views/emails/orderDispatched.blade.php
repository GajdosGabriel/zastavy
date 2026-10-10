@php
    $selection = collect($shipping->prepared_items ?? [])->keyBy('order_product_id');
    $remaining = $order->orderProducts->map(function ($item) use ($shipping, $selection) {
        $dispatching = $shipping->dispatched_at ? 0 : ($selection->get($item->id)['quantity'] ?? 0);
        return ['item' => $item, 'name' => $item->product_details->name, 'unit' => $item->product_details->unit_value,
            'quantity' => max(0, $item->quantity - $item->storno - $item->stockSum - $dispatching)];
    })->filter(fn ($item) => $item['quantity'] > 0);
    // Riadky dodacieho listu doplníme o položku objednávky, aby mali náhľad
    // a variant rovnako ako v potvrdení objednávky.
    $sent = collect($shipping->prepared_items ?? [])
        ->map(fn ($row) => $row + ['item' => $order->orderProducts->firstWhere('id', $row['order_product_id'] ?? null)]);
@endphp
<x-email.layout title="Potvrdenie odoslania" heading="Potvrdenie odoslania"
                :subheading="$order->serial_number ? 'Objednávka č. '.$order->serial_number : null">
    <p class="lead">
        Dobrý deň, <strong>{{ $order->billing->company ?: $order->billing->name }}</strong>,<br>
        nižšie uvedené položky Vašej objednávky sme vybavili a zásielku odoslali.
    </p>

    <div class="sent-box">
        <div class="box-title">Odoslané položky</div>
        <table>
            <thead>
                <tr>
                    <th>Tovar</th>
                    <th style="text-align:right">Množstvo</th>
                </tr>
            </thead>
            <tbody>
                @foreach($sent as $item)
                <tr>
                    <td>@if($item['item'])<x-email.order-item :item="$item['item']" />@else{{ $item['name'] }}@endif</td>
                    <td style="text-align:right; white-space:nowrap">{{ $item['quantity'] }} {{ $item['unit'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if($remaining->isNotEmpty())
    <div class="pending-box">
        <div class="box-title">Zostáva vybaviť</div>
        <table>
            <thead>
                <tr>
                    <th>Tovar</th>
                    <th style="text-align:right">Zostatok</th>
                </tr>
            </thead>
            <tbody>
                @foreach($remaining as $item)
                <tr>
                    <td>@if($item['item'])<x-email.order-item :item="$item['item']" />@else{{ $item['name'] }}@endif</td>
                    <td style="text-align:right; white-space:nowrap">{{ $item['quantity'] }} {{ $item['unit'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <p>O odoslaní ďalšej časti Vás budeme informovať.</p>
    </div>
    @endif

    {{-- Adresa, na ktorú balík naozaj ide — nech ju zákazník skontroluje,
         kým je zásielka ešte na ceste. --}}
    <x-email.delivery-address :order="$order" title="Zásielka ide na adresu" />

    @if($order->shipping_label)
    <div class="shipping-line">
        Spôsob doručenia: <strong>{{ $order->shipping_label }}</strong>
    </div>
    @endif

    <div class="button-row">
        <a href="{{ $order->publicUrl() }}" class="button">Zobraziť objednávku online</a>
    </div>
</x-email.layout>
