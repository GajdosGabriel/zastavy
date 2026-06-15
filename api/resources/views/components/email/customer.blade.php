@props(['customer'])
<div class="info-block">
    <p class="section-title">Zákazník</p>
    @if($customer->company)
        <p><strong>{{ $customer->company }}</strong></p>
    @endif
    @if($customer->name && trim($customer->name) !== trim($customer->company ?? ''))
        <p>{{ $customer->name }}</p>
    @endif
    @if($customer->ico)
        <p style="font-size:13px; color:#666;">IČO: {{ $customer->ico }}</p>
    @endif
    @if($customer->email)
        <p>{{ $customer->email }}</p>
    @endif
    @if($customer->phone)
        <p>{{ $customer->phone }}</p>
    @endif
</div>
