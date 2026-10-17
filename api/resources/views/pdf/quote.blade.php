@php
    $money = fn ($value) => number_format((float) $value, 2, ',', ' ') . ' €';
    $pct = fn ($value) => rtrim(rtrim(number_format((float) $value, 2, ',', ''), '0'), ',') . ' %';
    $totals = $version->totals ?? [];
    $customer = $quote->customer ?? [];
    $hasAlternatives = collect($version->items)->contains(fn ($i) => ! empty($i['alternatives']));
@endphp
<!DOCTYPE html>
<html lang="sk">
<head>
<meta charset="utf-8">
<title>Cenová ponuka {{ $quote->number }}</title>
<style>
    @page { margin: 28px 34px 50px; }
    * { font-family: DejaVu Sans, sans-serif; }
    body { font-size: 10px; color: #1f2937; }
    h1 { font-size: 20px; margin: 0; color: #1e3a5f; }
    .muted { color: #6b7280; }
    .small { font-size: 8.5px; }
    table { width: 100%; border-collapse: collapse; }
    .head td { vertical-align: top; }
    .box { border: 1px solid #d1d5db; padding: 8px 10px; }
    .label { font-size: 8px; text-transform: uppercase; letter-spacing: .5px; color: #6b7280; margin-bottom: 3px; }
    .items th { background: #1e3a5f; color: #fff; font-weight: bold; padding: 5px 6px; text-align: left; font-size: 8.5px; }
    .items td { padding: 5px 6px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
    .items .alt td { border-bottom: 1px dotted #e5e7eb; color: #374151; font-size: 9px; padding-top: 2px; padding-bottom: 3px; }
    .r, .items th.r { text-align: right; white-space: nowrap; }
    .totals td { padding: 3px 6px; }
    .grand td { font-size: 13px; font-weight: bold; color: #1e3a5f; border-top: 2px solid #1e3a5f; padding-top: 6px; }
    .footer { position: fixed; bottom: -32px; left: 0; right: 0; font-size: 8px; color: #6b7280; border-top: 1px solid #e5e7eb; padding-top: 4px; }
</style>
</head>
<body>
<div class="footer">
    {{ $company['name'] }} · IČO {{ $company['ico'] }} · DIČ {{ $company['dic'] }} · {{ $company['phone'] }} · {{ $company['email'] }} · {{ $company['web'] }}
</div>

<table class="head">
    <tr>
        <td style="width:55%;">
            <div style="font-size:14px; font-weight:bold; color:#1e3a5f;">{{ $company['name'] }}</div>
            <div>Sídlo: {{ $company['address'] }}</div>
            @if($company['office'])<div>Prevádzka: {{ $company['office'] }}</div>@endif
            <div>IČO: {{ $company['ico'] }} &nbsp; DIČ: {{ $company['dic'] }}</div>
            @if($company['register'])<div class="small muted">{{ $company['register'] }}</div>@endif
            <div>Tel.: {{ $company['phone'] }} &nbsp; {{ $company['email'] }}</div>
        </td>
        <td style="text-align:right;">
            <h1>CENOVÁ PONUKA</h1>
            <div style="font-size:12px; font-weight:bold;">č. {{ $quote->number ?? $quote->id }}{{ $version->version > 1 ? ' (verzia '.$version->version.')' : '' }}</div>
            <div style="margin-top:6px;">Dátum vystavenia: <strong>{{ ($version->sent_at ?? now())->format('j. n. Y') }}</strong></div>
            @if($version->valid_until)<div>Platnosť do: <strong>{{ $version->valid_until->format('j. n. Y') }}</strong></div>@endif
        </td>
    </tr>
</table>

<table style="margin-top:14px;">
    <tr>
        <td style="width:55%; padding-right:10px; vertical-align:top;">
            <div class="box">
                <div class="label">Odberateľ</div>
                <strong>{{ $quote->displayName() }}</strong><br>
                @if(! empty($customer['street'])){{ $customer['street'] }}<br>@endif
                @if(! empty($customer['city'])){{ $customer['postcode'] ?? '' }} {{ $customer['city'] }}<br>@endif
                @if(! empty($customer['ico']))IČO: {{ $customer['ico'] }}@endif
                @if(! empty($customer['dic'])) &nbsp; DIČ: {{ $customer['dic'] }}@endif
                @if(! empty($customer['ic_dic'])) &nbsp; IČ DPH: {{ $customer['ic_dic'] }}@endif
            </div>
        </td>
        <td style="vertical-align:top;">
            <div class="box">
                <div class="label">Kontaktná osoba</div>
                {{ $customer['name'] ?? '' }}<br>
                {{ $customer['email'] ?? '' }}<br>
                {{ $customer['phone'] ?? '' }}
            </div>
        </td>
    </tr>
</table>

@if($quote->title)
<p style="margin:14px 0 4px; font-size:12px;"><strong>Predmet:</strong> {{ $quote->title }}</p>
@endif

<table class="items" style="margin-top:10px;">
    <thead>
        <tr>
            <th style="width:4%;">#</th>
            <th style="width:38%;">Položka</th>
            <th class="r">Množstvo</th>
            <th class="r">Cena/j. bez DPH</th>
            <th class="r">Zľava</th>
            <th class="r">DPH</th>
            <th class="r">Spolu bez DPH</th>
        </tr>
    </thead>
    <tbody>
    @foreach($version->items as $n => $item)
        <tr>
            <td>{{ $n + 1 }}.</td>
            <td>
                <strong>{{ $item['snapshot']['name'] }}</strong>@if($item['snapshot']['variant_name'] ?? null) — {{ $item['snapshot']['variant_name'] }}@endif
                @if($item['snapshot']['code'] ?? null)<span class="muted small"> ({{ $item['snapshot']['variant_code'] ?? $item['snapshot']['code'] }})</span>@endif
                @if($item['description'] ?? null)<br><span class="muted">{!! nl2br(e($item['description'])) !!}</span>@endif
            </td>
            <td class="r">{{ $item['quantity'] }} {{ $item['snapshot']['unit_value'] ?? 'ks' }}</td>
            <td class="r">{{ $money($item['price_net'] ?? $item['price']) }}</td>
            <td class="r">{{ ($item['discount_percent'] ?? 0) > 0 ? $pct($item['discount_percent']) : '' }}</td>
            <td class="r">{{ $item['snapshot']['vat'] ?? '' }} %</td>
            <td class="r">{{ $money($item['line_net'] ?? $item['quantity'] * $item['price']) }}</td>
        </tr>
        @foreach($item['alternatives'] ?? [] as $alt)
        <tr class="alt">
            <td></td>
            <td style="padding-left:14px;">alebo pri odbere {{ $alt['quantity'] }} {{ $item['snapshot']['unit_value'] ?? 'ks' }}</td>
            <td class="r">{{ $alt['quantity'] }} {{ $item['snapshot']['unit_value'] ?? 'ks' }}</td>
            <td class="r">{{ $money($alt['price_net']) }}</td>
            <td class="r">{{ ($alt['discount_percent'] ?? 0) > 0 ? $pct($alt['discount_percent']) : '' }}</td>
            <td class="r">{{ $item['snapshot']['vat'] ?? '' }} %</td>
            <td class="r">{{ $money($alt['line_net']) }}</td>
        </tr>
        @endforeach
    @endforeach
    </tbody>
</table>

<table style="margin-top:8px;">
    <tr>
        <td style="width:50%; vertical-align:top; padding-right:12px;">
            @if($hasAlternatives)
            <p class="small muted">Pri položkách s viacerými množstvami si vyberiete jednu možnosť pri prijatí ponuky. Súčet je vypočítaný pre prvé uvedené množstvá.</p>
            @endif
        </td>
        <td style="vertical-align:top;">
            <table class="totals">
                @if(($totals['discount_net'] ?? 0) > 0)
                <tr><td>Položky spolu bez DPH</td><td class="r">{{ $money($totals['items_net']) }}</td></tr>
                <tr><td>Zľava {{ $pct($totals['discount_percent']) }}</td><td class="r">−{{ $money($totals['discount_net']) }}</td></tr>
                @endif
                @if($version->shipping)
                <tr><td>Doprava ({{ $version->shipping['name'] }})</td><td class="r">{{ $money($version->shipping['price_net'] ?? $version->shipping['price']) }}</td></tr>
                @endif
                <tr><td>Spolu bez DPH</td><td class="r">{{ $money($totals['net'] ?? 0) }}</td></tr>
                <tr><td>DPH</td><td class="r">{{ $money($totals['vat'] ?? 0) }}</td></tr>
                <tr class="grand"><td>Spolu s DPH</td><td class="r">{{ $money($totals['gross'] ?? 0) }}</td></tr>
            </table>
        </td>
    </tr>
</table>

@if(filled($version->terms))
<div style="margin-top:14px;">
    <div class="label">Podmienky a termín dodania</div>
    <div>{!! nl2br(e($version->terms)) !!}</div>
</div>
@endif

<div style="margin-top:14px;" class="small">
    Ponuku môžete prijať online cez odkaz v e-maile – prijatím vznikne záväzná objednávka.
    @if($bank['iban'] ?? null)<br>Bankové spojenie: {{ $bank['holder'] }}, IBAN {{ $bank['iban'] }}@if($bank['swift'] ?? null), SWIFT {{ $bank['swift'] }}@endif.@endif
</div>

<p style="margin-top:22px;">S pozdravom<br><strong>{{ $quote->creator?->name ?: $company['name'] }}</strong><br>{{ $company['phone'] }} · {{ $company['email'] }}</p>
</body>
</html>
