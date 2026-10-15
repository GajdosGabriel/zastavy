<x-email.layout title="Zmena objednávky" heading="Zmena objednávky"
                :subheading="$order->serial_number ? 'Objednávka č. '.$order->serial_number : null">
    <x-slot:styles>
        <style>
            .change-block { background: #f0f4ff; border-left: 4px solid #1e3a5f; border-radius: 4px; padding: 12px 20px; margin: 0 0 24px; }
            .change-block td { border-bottom: none; padding: 5px 0; }
            .change-label { color: #555; padding-right: 12px !important; }
            .change-old { color: #b91c1c; text-decoration: line-through; }
            .change-new { color: #166534; font-weight: 600; }
        </style>
    </x-slot:styles>

    <p class="lead">
        Dobrý deň, <strong>{{ $order->billing->company ?: $order->billing->name }}</strong>,<br>
        Vašu objednávku sme upravili. Nižšie nájdete, čo sa zmenilo, a aktuálnu rekapituláciu.
    </p>

    @if(!empty($changes))
    <p class="section-title">Čo sa zmenilo</p>
    <div class="change-block">
        <table role="presentation" style="margin:0;">
            @foreach($changes as $change)
            <tr>
                <td class="change-label">{{ $change['label'] }}</td>
                <td>
                    <span class="change-old">{{ $change['old'] ?? '—' }}</span>
                    <span style="color:#555;"> → </span>
                    <span class="change-new">{{ $change['new'] ?? '—' }}</span>
                </td>
            </tr>
            @endforeach
        </table>
    </div>
    @endif

    <x-email.order-summary :order="$order" title="Aktuálne položky" />

    <x-email.payment :order="$order" />

    <div class="button-row">
        <a href="{{ $order->publicUrl() }}" class="button">Zobraziť objednávku online</a>
    </div>

    <x-email.messages-link :order="$order" />
</x-email.layout>
