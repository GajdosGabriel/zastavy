<x-email.layout title="Obnovenie hesla" heading="Obnovenie hesla">
    <p class="lead">
        Dobrý deň,<br>
        dostali sme požiadavku na obnovenie hesla k Vášmu účtu. Kliknite na tlačidlo nižšie a nastavte si nové heslo.
    </p>

    <div class="button-row">
        <a href="{{ $url }}" class="button">Nastaviť nové heslo</a>
    </div>

    <p class="lead">
        Platnosť odkazu vyprší o <strong>60 minút</strong>.
        Ak ste o obnovenie hesla nežiadali, tento e-mail ignorujte — Vaše heslo zostane nezmenené.
    </p>

    <p class="muted" style="word-break: break-all;">
        Ak tlačidlo nefunguje, skopírujte tento odkaz do prehliadača:<br>{{ $url }}
    </p>
</x-email.layout>
