@props(['order', 'title' => 'Adresa doručenia'])
@php
    $delivery = $order->deliverySnapshot();
    $customer = $order->customer;
@endphp

<div class="info-block">
    <p class="section-title">{{ $title }}</p>
    @if($delivery['company'])
        <p><strong>{{ $delivery['company'] }}</strong></p>
    @endif
    @if($delivery['name'])
        <p>{{ $delivery['name'] }}</p>
    @endif
    @if($delivery['street'])
        <p>{{ $delivery['street'] }}</p>
    @endif
    <p>{{ trim($delivery['postcode'].' '.$delivery['city']) }}</p>
    @if($delivery['phone'])
        <p style="font-size:13px; color:#666;">Tel.: {{ $delivery['phone'] }}</p>
    @endif
    @if($delivery['note'])
        <p style="font-size:13px; color:#666;">{{ $delivery['note'] }}</p>
    @endif

    {{-- Fakturačnú adresu ukazujeme len vtedy, keď sa líši — inak by to bol
         ten istý údaj dvakrát pod sebou. --}}
    @if($delivery['is_custom'] && ($customer?->street || $customer?->city))
        <p style="margin-top:12px; font-size:12px; text-transform:uppercase; letter-spacing:0.5px; color:#888; font-weight:700;">Fakturačná adresa</p>
        <p style="font-size:13px; color:#666;">
            {{ $customer->company }}@if($customer->street), {{ $customer->street }}@endif, {{ $customer->postcode }} {{ $customer->city }}
        </p>
    @endif
</div>
