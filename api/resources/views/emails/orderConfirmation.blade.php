@php($forAdmin = $forAdmin ?? false)
<x-email.layout :title="$forAdmin ? 'Nová objednávka' : 'Potvrdenie objednávky'"
                :heading="$forAdmin ? 'Nová objednávka' : 'Ďakujeme za objednávku'"
                :subheading="$order->serial_number ? 'Objednávka č. '.$order->serial_number : null"
                :contact="! $forAdmin">
    @if($forAdmin)
    <p class="lead">
        Prišla nová objednávka od <strong>{{ $order->billing->company ?: $order->billing->name }}</strong>
        na <strong>{{ number_format($order->grandTotal(), 2, ',', ' ') }} €</strong>. Odpoveď na tento e-mail pôjde zákazníkovi.
    </p>
    @else
    <p class="lead">
        Dobrý deň, <strong>{{ $order->billing->company ?: $order->billing->name }}</strong>,<br>
        Vašu objednávku sme prijali. Skontrolujeme dostupnosť položiek a ozveme sa, keď ju začneme vybavovať.
    </p>

    {{-- Pri platbe prevodom hneď IBAN a variabilný symbol — zákazník ich potrebuje ako prvé. --}}
    <x-email.payment :order="$order" />
    @endif

    <x-email.order-summary :order="$order" />

    @if($order->shipping_label || $order->payment_label)
    <div class="shipping-line">
        @if($order->shipping_label)Doprava: <strong>{{ $order->shipping_label }}</strong>@endif
        @if($order->shipping_label && $order->payment_label) &nbsp;·&nbsp; @endif
        @if($order->payment_label)Platba: <strong>{{ $order->payment_label }}</strong>@endif
    </div>
    @endif

    <x-email.customer :customer="$order->billing" :order="$order" />

    @unless($order->deliveryMatchesBilling())
        <x-email.delivery-address :order="$order" />
    @endunless

    {{-- Adresu si zákazník vie opraviť sám, kým objednávka nejde do expedície. --}}
    @if(! $forAdmin && $order->canEditDelivery() && $order->deliveryEditUrl())
    <table role="presentation" style="margin:-8px 0 24px; background:#eff6ff; border:1px solid #bfdbfe; border-radius:5px;">
        <tr>
            <td class="cta-text" style="padding:14px 18px; border-bottom:none; font-size:13px; color:#1e3a5f;">
                <strong style="display:block; font-size:14px; margin-bottom:3px;">Doručiť inam?</strong>
                Adresu doručenia si môžete zmeniť sami, kým objednávku neodošleme.
            </td>
            <td class="cta-action" style="padding:14px 18px 14px 0; border-bottom:none; text-align:right; white-space:nowrap; vertical-align:middle;">
                <a href="{{ $order->deliveryEditUrl() }}"
                   style="display:inline-block; background:#fff; color:#1e3a5f; border:1px solid #1e3a5f; text-decoration:none; padding:8px 16px; border-radius:5px; font-size:13px; font-weight:600;">
                    Zmeniť adresu
                </a>
            </td>
        </tr>
    </table>
    @endif

    @if(!empty(trim($order->note ?? '')))
    <div class="info-block">
        <p class="section-title">Poznámka</p>
        <p>{{ $order->note }}</p>
    </div>
    @endif

    @if($order->wants_coupon)
    <div class="warn-box">
        <strong style="display:block; font-size:14px; margin-bottom:3px;">Záujem o zľavový kupón</strong>
        Zaregistrovali sme Váš záujem o zľavový kupón na ďalší nákup. Kupón Vám pošleme spolu s expedíciou objednávky.
    </div>
    @endif

    <div class="button-row">
        {{-- S tokenom sa na detaile ukáže aj odkaz na zmenu adresy. --}}
        <a href="{{ $order->publicUrl() }}{{ $order->delivery_token ? '?token='.$order->delivery_token : '' }}" class="button">
            Zobraziť objednávku online
        </a>
    </div>

    @unless($forAdmin)
        <x-email.messages-link :order="$order" />
    @endunless
</x-email.layout>
