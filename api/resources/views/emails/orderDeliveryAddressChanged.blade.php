@php
    // Adresa do jedného riadku; meno sa nezopakuje, keď je rovnaké ako „firma“ (súkromná osoba).
    $line = function (array $address) {
        $name = trim((string) ($address['name'] ?? ''));
        $company = trim((string) ($address['company'] ?? ''));

        return collect([
            $company,
            $name !== $company ? $name : null,
            $address['street'] ?? null,
            trim(($address['postcode'] ?? '').' '.($address['city'] ?? '')),
        ])->filter()->implode(', ');
    };
@endphp
<x-email.layout title="Zmena adresy doručenia" heading="Zmena adresy doručenia"
                :subheading="$order->serial_number ? 'Objednávka č. '.$order->serial_number : null">
    <x-slot:styles>
        <style>
            .compare td { vertical-align: top; width: 50%; padding: 14px 16px; font-size: 14px; line-height: 1.5; }
            .compare .old { background: #f7f7f7; color: #555; text-decoration: line-through; }
            .compare .new { background: #f0fdf4; color: #166534; border-left: 3px solid #16a34a; }
            .compare th { padding: 0 16px 6px; }
        </style>
    </x-slot:styles>

    <p class="lead">
        Dobrý deň, <strong>{{ $order->billing->company ?: $order->billing->name }}</strong>,<br>
        adresu doručenia objednávky sme upravili{!! $order->delivery_changed_by ? ' — zmenu zadal <strong>'.e($order->delivery_changed_by).'</strong>' : '' !!}.
        Tovar pošleme na novú adresu.
    </p>

    <table class="compare">
        <thead>
            <tr>
                <th>Pôvodná adresa</th>
                <th>Nová adresa</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="old">{{ $line($previous) }}</td>
                <td class="new">
                    {{ $line($current) }}
                    @if($current['phone'] ?? null)<br><span style="font-size:13px;">Tel.: {{ $current['phone'] }}</span>@endif
                    @if($current['note'] ?? null)<br><span style="font-size:13px;">{{ $current['note'] }}</span>@endif
                </td>
            </tr>
        </tbody>
    </table>

    <p class="muted">
        Ak ste zmenu nezadali vy, ozvite sa nám čo najskôr na
        <a href="mailto:obchod@zastavy-vlajky.sk" style="color:#1e3a5f;">obchod&#64;zastavy-vlajky.sk</a> — adresu vrátime späť.
    </p>

    <div class="button-row">
        <a href="{{ $order->publicUrl() }}" class="button">Zobraziť objednávku online</a>
    </div>
</x-email.layout>
