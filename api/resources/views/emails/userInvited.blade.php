@php
    // Technické názvy rolí → slovensky (rovnako ako vo filtri používateľov).
    $roleLabels = [
        'super-admin' => 'Super admin', 'admin' => 'Admin', 'manager' => 'Manažér',
        'sales' => 'Obchod', 'warehouse' => 'Sklad', 'customer' => 'Zákazník',
    ];
@endphp
<x-email.layout title="Váš účet bol vytvorený" heading="Váš účet bol vytvorený">
    <x-slot:styles>
        <style>
            .credentials { background: #f0f4ff; border-left: 4px solid #1e3a5f; border-radius: 4px; padding: 12px 20px; margin: 0 0 24px; }
            .credentials td { border-bottom: none; padding: 6px 0; }
            .credentials code { font-family: monospace; font-size: 15px; background: #e0e8ff; padding: 2px 6px; border-radius: 3px; }
            .role { display: inline-block; background: #e8f0fe; color: #1a3a8f; border-radius: 12px; padding: 3px 12px; font-size: 13px; margin: 2px; }
        </style>
    </x-slot:styles>

    <p class="lead">
        Dobrý deň, <strong>{{ $user->firstName }}</strong>,<br>
        bol Vám vytvorený prístupový účet{!! $user->customer ? ' pre zákazníka <strong>'.e($user->customer->company).'</strong>' : '' !!}.
        Nižšie nájdete prihlasovacie údaje.
    </p>

    <div class="credentials">
        <table role="presentation" style="margin:0;">
            <tr>
                <td style="width:130px; color:#555; font-weight:700;">E-mail</td>
                <td><code>{{ $user->email }}</code></td>
            </tr>
            <tr>
                <td style="width:130px; color:#555; font-weight:700;">Dočasné heslo</td>
                <td><code>{{ $temporaryPassword }}</code></td>
            </tr>
        </table>
    </div>

    @if(count($roles) > 0)
    <div class="info-block">
        <p class="section-title">Pridelené role</p>
        @foreach($roles as $role)
            <span class="role">{{ $roleLabels[$role] ?? $role }}</span>
        @endforeach
    </div>
    @endif

    @if($verificationUrl)
    <p class="lead">Pred prihlásením je potrebné overiť Váš e-mail kliknutím na tlačidlo nižšie. Až potom bude Váš účet aktívny.</p>
    <div class="button-row">
        <a href="{{ $verificationUrl }}" class="button">Overiť e-mail a aktivovať účet</a>
    </div>
    @else
    <div class="button-row">
        <a href="{{ $loginUrl }}" class="button">Prihlásiť sa</a>
    </div>
    @endif

    <div class="warn-box">
        <strong>Odporúčame</strong> zmeniť heslo ihneď po prvom prihlásení.
    </div>
</x-email.layout>
