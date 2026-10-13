@php
    $artwork = $entry->kind === 'artwork_sent' ? $entry->artworkVersion : null;
@endphp
<x-email.layout title="Správa k objednávke"
                :heading="$artwork ? 'Grafický návrh na schválenie' : 'Správa k Vašej objednávke'"
                :subheading="$order->serial_number ? 'Objednávka č. '.$order->serial_number : null">
    <p class="lead">
        Dobrý deň, <strong>{{ $order->billing->company ?: $order->billing->name }}</strong>,
    </p>
    <p class="lead">{!! nl2br(e($entry->body)) !!}</p>

    @if($artwork)
    <div class="note-box">
        <strong>Grafický návrh – verzia {{ $artwork->version }}</strong> ({{ $artwork->name }})<br>
        Návrh si prezrite cez tlačidlo nižšie. Na tej istej stránke ho schválite na výrobu alebo nám napíšete pripomienky.
    </div>
    @endif

    @if($entry->attachments->isNotEmpty())
    <p class="section-title">Prílohy</p>
    <div class="info-block">
        @foreach($entry->attachments as $file)
            <p>{{ $file->name }}</p>
        @endforeach
        <p style="color:#888; font-size:13px;">Prílohy si stiahnete na stránke správ.</p>
    </div>
    @endif

    <div class="button-row">
        <a href="{{ $url }}" class="button">{{ $artwork ? 'Zobraziť návrh a odpovedať' : 'Zobraziť a odpovedať' }}</a>
    </div>

    <div class="note-box">
        Odpovedzte, prosím, cez tlačidlo vyššie – Vaša odpoveď sa tak uloží priamo k objednávke a nič sa nestratí.
    </div>
</x-email.layout>
