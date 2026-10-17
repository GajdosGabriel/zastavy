<x-email.layout title="Správa k ponuke" :heading="$heading" :subheading="'Cenová ponuka č. '.$quote->number">
    <p class="lead">
        <strong>{{ $entry->author }}</strong> ({{ $quote->displayName() }})
        @if($entry->kind === 'rejected')
            odmietol/a ponuku verzie {{ $quote->version }}.
        @elseif($entry->kind === 'change_request')
            žiada úpravu ponuky:
        @else
            napísal/a k ponuke:
        @endif
    </p>

    <div class="note-box">{!! nl2br(e($entry->body)) !!}</div>

    <div class="button-row">
        <a href="{{ $url }}" class="button">Otvoriť ponuku</a>
    </div>
</x-email.layout>
