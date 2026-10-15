@php
    $messages = [
        'not_accepted' => [
            'title' => 'Vrátená zásielka',
            'body'  => 'Vaša zásielka sa nám vrátila ako neprevzatá. Kontaktujte nás, prosím, aby sme dohodli ďalší postup — opätovné doručenie alebo storno objednávky.',
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
<x-email.layout :title="$msg['title']" :heading="$msg['title']" accent="#92400e"
                :subheading="$order->serial_number ? 'Objednávka č. '.$order->serial_number : null">
    <p class="lead">
        Dobrý deň, <strong>{{ $order->billing->company ?: $order->billing->name }}</strong>,<br>
        {{ $msg['body'] }}
    </p>

    @if($msg['action'])
    <div class="note-box">{{ $msg['action'] }}</div>
    @endif

    @if($orderReturn->note)
    <div class="warn-box">
        <strong style="display:block; margin-bottom:4px;">Poznámka</strong>
        {{-- Poznámka sa zadáva v HTML editore — pred vypísaním ju prečistíme. --}}
        {!! \App\Services\HtmlSanitizer::sanitize($orderReturn->note) !!}
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
                <td>
                    @if($item->orderProduct)<x-email.order-item :item="$item->orderProduct" />@else—@endif
                </td>
                <td style="text-align:right; white-space:nowrap">
                    {{ $item->quantity }} {{ $item->orderProduct?->product_details?->unit_value ?? 'ks' }}
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="button-row">
        <a href="{{ $order->publicUrl() }}" class="button">Zobraziť objednávku online</a>
    </div>

    <x-email.messages-link :order="$order" />
</x-email.layout>
