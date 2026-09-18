@props(['customer', 'order' => null])
@php
    // Keď balík ide na fakturačnú adresu, spojíme zákazníka a adresu doručenia
    // do jedného bloku — inak by sa tie isté údaje opakovali dvakrát pod sebou.
    $delivery = $order?->deliveryMatchesBilling() ? $order->deliverySnapshot() : null;
@endphp
<div class="info-block">
    <p class="section-title">{{ $delivery ? 'Zákazník a adresa doručenia' : 'Zákazník' }}</p>
    @if($customer->company)
        <p><strong>{{ $customer->company }}</strong></p>
        @if($delivery && $delivery['name'] && $delivery['name'] !== $customer->company)
            <p>{{ $delivery['name'] }}</p>
        @endif
    @else
        <p>{{ $delivery['name'] ?? $customer->name }}</p>
    @endif
    @if($delivery)
        @if($delivery['street'])
            <p>{{ $delivery['street'] }}</p>
        @endif
        @if(trim($delivery['postcode'].' '.$delivery['city']))
            <p>{{ trim($delivery['postcode'].' '.$delivery['city']) }}</p>
        @endif
    @endif
    @if($customer->ico)
        <p style="font-size:13px; color:#666;">IČO: {{ $customer->ico }}</p>
    @endif
    @if($customer->email)
        <p>{{ $customer->email }}</p>
    @endif
    @if($delivery && $delivery['phone'])
        <p>{{ $delivery['phone'] }}</p>
    @elseif($customer->phone)
        <p>{{ $customer->phone }}</p>
    @endif
    @if($delivery && $delivery['note'])
        <p style="font-size:13px; color:#666;">{{ $delivery['note'] }}</p>
    @endif
</div>
