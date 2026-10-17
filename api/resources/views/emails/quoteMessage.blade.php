<x-email.layout title="Správa k ponuke" heading="Správa k Vašej cenovej ponuke" :subheading="'Cenová ponuka č. '.$quote->number">
    <p class="lead">
        Dobrý deň, <strong>{{ $quote->displayName() }}</strong>,
    </p>
    <p class="lead">{!! nl2br(e($entry->body)) !!}</p>

    <div class="button-row">
        <a href="{{ $url }}" class="button">Zobraziť ponuku a odpovedať</a>
    </div>
</x-email.layout>
