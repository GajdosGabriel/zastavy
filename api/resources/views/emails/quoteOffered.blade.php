@php
    $money = fn ($value) => number_format((float) $value, 2, ',', ' ') . ' €';
    $totals = $version->totals ?? [];
@endphp
<x-email.layout title="Cenová ponuka"
                heading="Cenová ponuka"
                :subheading="'č. '.$quote->number.($version->version > 1 ? ', verzia '.$version->version : '')">
    <p class="lead">
        Dobrý deň, <strong>{{ $quote->displayName() }}</strong>,<br>
        @if($version->version > 1)
            posielame Vám upravenú cenovú ponuku{{ $quote->title ? ' – '.$quote->title : '' }}. Predchádzajúca verzia už neplatí.
        @else
            ďakujeme za Váš dopyt. Posielame Vám cenovú ponuku{{ $quote->title ? ' – '.$quote->title : '' }}.
        @endif
        Ponuku nájdete aj v prílohe tohto e-mailu (PDF).
    </p>

    <div class="button-row">
        <a href="{{ $acceptUrl }}" class="button">Prijať ponuku a objednať</a>
    </div>
    <p style="margin-top:-8px; text-align:center; color:#555; font-size:13px;">
        Tlačidlo otvorí ponuku, kde objednávku jedným kliknutím potvrdíte.
    </p>

    <p class="section-title">Položky</p>
    <table>
        <thead>
            <tr>
                <th>Položka</th>
                <th style="text-align:right; padding-left:10px;">Množstvo</th>
                <th style="text-align:right; padding-left:10px;">Cena/j. bez DPH</th>
                <th style="text-align:right; padding-left:10px;">Spolu bez DPH</th>
            </tr>
        </thead>
        <tbody>
            @foreach($version->items as $item)
            <tr>
                <td>
                    <strong>{{ $item['snapshot']['name'] }}</strong>@if($item['snapshot']['variant_name'] ?? null) — {{ $item['snapshot']['variant_name'] }}@endif
                    @if($item['description'] ?? null)<br><span style="color:#555; font-size:13px;">{{ $item['description'] }}</span>@endif
                    @if(($item['discount_percent'] ?? 0) > 0)<br><span style="color:#15803d; font-size:12px;">zľava {{ rtrim(rtrim(number_format($item['discount_percent'], 2, ',', ''), '0'), ',') }} %</span>@endif
                </td>
                <td style="text-align:right; white-space:nowrap; padding-left:10px;">{{ $item['quantity'] }} {{ $item['snapshot']['unit_value'] ?? 'ks' }}</td>
                <td style="text-align:right; white-space:nowrap; padding-left:10px;">{{ $money($item['unit_net'] ?? $item['price']) }}</td>
                <td style="text-align:right; white-space:nowrap; padding-left:10px;">{{ $money($item['line_net'] ?? $item['quantity'] * $item['price']) }}</td>
            </tr>
            @endforeach
            @if(($totals['discount_net'] ?? 0) > 0)
            <tr>
                <td colspan="3" style="color:#15803d;">Zľava {{ rtrim(rtrim(number_format($totals['discount_percent'], 2, ',', ''), '0'), ',') }} %</td>
                <td style="text-align:right; color:#15803d; white-space:nowrap">−{{ $money($totals['discount_net']) }}</td>
            </tr>
            @endif
            @if($version->shipping)
            <tr>
                <td colspan="3" style="color:#555;">Doprava ({{ $version->shipping['name'] }})</td>
                <td style="text-align:right; color:#555; white-space:nowrap">{{ $money($version->shipping['price_net'] ?? $version->shipping['price']) }}</td>
            </tr>
            @endif
            <tr>
                <td colspan="3">Spolu bez DPH</td>
                <td style="text-align:right; white-space:nowrap">{{ $money($totals['net'] ?? 0) }}</td>
            </tr>
            <tr>
                <td colspan="3">DPH</td>
                <td style="text-align:right; white-space:nowrap">{{ $money($totals['vat'] ?? 0) }}</td>
            </tr>
            <tr>
                <td colspan="3"><strong>Spolu s DPH</strong></td>
                <td style="text-align:right; white-space:nowrap"><strong>{{ $money($totals['gross'] ?? 0) }}</strong></td>
            </tr>
        </tbody>
    </table>

    @if(filled($version->terms))
    <div class="info-block">
        <p class="section-title">Podmienky a termín</p>
        <p>{!! nl2br(e($version->terms)) !!}</p>
    </div>
    @endif

    <div class="note-box">
        Ponuka platí do <strong>{{ $version->valid_until?->format('j. n. Y') }}</strong>. Prijať ju môžete online – prijatím vznikne záväzná objednávka.
    </div>

    <div class="button-row">
        <a href="{{ $acceptUrl }}" class="button">Prijať ponuku a objednať</a>
    </div>

    <p style="color:#555; font-size:13px;">
        Chcete ponuku upraviť alebo máte otázku? <a href="{{ $url }}">Napíšte nám pri ponuke</a> alebo odpovedzte na tento e-mail.<br>
        Bežný tovar si môžete objednať aj priamo v našom e-shope <a href="{{ $shopUrl }}">{{ preg_replace('#^https?://#', '', $shopUrl) }}</a>.
    </p>
</x-email.layout>
