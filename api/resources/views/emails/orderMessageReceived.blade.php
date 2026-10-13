@php
    $headings = [
        'approved' => 'Zákazník schválil grafiku',
        'changes_requested' => 'Pripomienky ku grafike',
        'message' => 'Nová správa od zákazníka',
    ];
    $version = $entry->artworkVersion?->version;
@endphp
<x-email.layout title="Správa od zákazníka" :heading="$headings[$entry->kind] ?? $headings['message']"
                :subheading="$order->serial_number ? 'Objednávka č. '.$order->serial_number : null">
    <p class="lead">
        <strong>{{ $entry->author }}</strong> ({{ $order->billing->company ?: $order->billing->name }})
        @if($entry->kind === 'approved')
            schválil/a verziu {{ $version }} grafického návrhu na výrobu.
        @elseif($entry->kind === 'changes_requested')
            poslal/a pripomienky k verzii {{ $version }} grafického návrhu:
        @else
            napísal/a k objednávke:
        @endif
    </p>

    @if(filled($entry->body))
    <div class="note-box">{!! nl2br(e($entry->body)) !!}</div>
    @endif

    @if($entry->attachments->isNotEmpty())
    <p class="section-title">Prílohy</p>
    <div class="info-block">
        @foreach($entry->attachments as $file)
            <p>{{ $file->name }}</p>
        @endforeach
    </div>
    @endif

    <div class="button-row">
        <a href="{{ $url }}" class="button">Otvoriť konverzáciu</a>
    </div>
</x-email.layout>
