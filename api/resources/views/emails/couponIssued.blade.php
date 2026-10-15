@php
    // „10 %“ alebo „5 €“ — percentuálny aj pevný kupón, bez zbytočných „.00“.
    $amount = rtrim(rtrim(number_format((float) $coupon->value, 2, ',', ' '), '0'), ',');
    $discountLabel = $coupon->type === 'fixed' ? $amount.' € zľava' : $amount.' % zľava';
    $minOrder = (float) $coupon->min_order_price;
@endphp
<x-email.layout title="Váš zľavový kupón" heading="Zľavový kupón pre Vás"
                :subheading="'Ďakujeme za Vašu objednávku č. '.$order->serial_number">
    <x-slot:styles>
        <style>
            .coupon-box { background: #f0fdf4; border: 2px dashed #16a34a; border-radius: 8px; padding: 24px 20px; text-align: center; margin: 0 0 24px; }
            .coupon-badge { display: inline-block; background: #15803d; color: #fff; font-size: 13px; font-weight: 700; border-radius: 20px; padding: 4px 16px; margin-bottom: 14px; }
            .coupon-code { font-size: 30px; font-weight: 700; letter-spacing: 5px; color: #14532d; font-family: monospace; margin: 0 0 12px; word-break: break-all; }
            .coupon-meta { font-size: 13px; color: #333; line-height: 1.8; }
        </style>
    </x-slot:styles>

    <p class="lead">
        Dobrý deň,<br>
        ako sme sľúbili, posielame Vám zľavový kupón na ďalší nákup.
    </p>

    <div class="coupon-box">
        <div class="coupon-badge">{{ $discountLabel }}</div>
        <div class="coupon-code">{{ $coupon->code }}</div>
        <div class="coupon-meta">
            Platí od <strong>{{ $coupon->valid_from->format('d. m. Y') }}</strong>
            do <strong>{{ $coupon->valid_to->format('d. m. Y') }}</strong><br>
            @if($minOrder > 0)
                Pri objednávke od <strong>{{ number_format($minOrder, 0, ',', ' ') }} €</strong><br>
            @endif
            Jednorazový — môžete ho použiť iba raz
        </div>
    </div>

    <p class="section-title">Ako uplatniť kupón</p>
    <div class="info-block">
        <p>1. Vložte tovar do košíka na zastavy-vlajky.sk.</p>
        <p>2. V košíku zvoľte <strong>„Mám kupón“</strong> a zadajte kód.</p>
        <p>3. Kliknite na <strong>„Uplatniť“</strong> — zľava sa odpočíta automaticky.</p>
    </div>

    <p class="muted">Kupón nie je možné kombinovať s inými akciami ani zľavami.</p>

    <div class="button-row">
        <a href="{{ config('app.frontend_url') }}" class="button">Nakupovať teraz</a>
    </div>
</x-email.layout>
