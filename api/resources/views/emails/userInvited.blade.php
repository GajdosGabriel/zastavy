<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Váš účet bol vytvorený</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.1); }
        .header { background: #1e3a5f; padding: 24px 32px; }
        .header h1 { color: #fff; margin: 0; font-size: 22px; }
        .body { padding: 32px; }
        .body p { color: #333; line-height: 1.6; margin: 0 0 16px; }
        .credentials { background: #f0f4ff; border-left: 4px solid #1e3a5f; border-radius: 4px; padding: 16px 20px; margin: 20px 0; }
        .credentials p { margin: 6px 0; font-size: 14px; color: #333; }
        .credentials strong { display: inline-block; width: 140px; color: #555; }
        .credentials code { font-family: monospace; font-size: 15px; background: #e0e8ff; padding: 2px 6px; border-radius: 3px; }
        .roles { margin: 20px 0; }
        .roles .role { display: inline-block; background: #e8f0fe; color: #1a3a8f; border-radius: 12px; padding: 3px 12px; font-size: 13px; margin: 2px; }
        .btn { display: inline-block; background: #1e3a5f; color: #fff !important; padding: 12px 28px; border-radius: 6px; text-decoration: none; font-weight: bold; margin: 20px 0; }
        .warning { background: #fff8e1; border-left: 4px solid #f9a825; padding: 12px 16px; border-radius: 4px; font-size: 13px; color: #5d4037; margin: 20px 0; }
        .footer { padding: 20px 32px; background: #f8f8f8; border-top: 1px solid #eee; font-size: 12px; color: #999; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>Váš účet bol vytvorený</h1>
    </div>
    <div class="body">
        <p>Dobrý deň, <strong>{{ $user->firstName }}</strong>,</p>

        <p>
            Bol Vám vytvorený prístupový účet
            @if($user->customer)
                pre zákazníka <strong>{{ $user->customer->company }}</strong>
            @endif
            . Nižšie nájdete prihlasovacie údaje.
        </p>

        <div class="credentials">
            <p><strong>Email:</strong> <code>{{ $user->email }}</code></p>
            <p><strong>Dočasné heslo:</strong> <code>{{ $temporaryPassword }}</code></p>
        </div>

        @if(count($roles) > 0)
        <div class="roles">
            <p><strong>Pridelené role:</strong></p>
            @foreach($roles as $role)
                <span class="role">{{ $role }}</span>
            @endforeach
        </div>
        @endif

        @if($verificationUrl)
        <p>Pred prihlásením je potrebné overiť Váš email kliknutím na tlačidlo nižšie. Až potom bude Váš účet aktívny.</p>
        <a href="{{ $verificationUrl }}" class="btn">Overiť email a aktivovať účet</a>
        @else
        <a href="{{ $loginUrl }}" class="btn">Prihlásiť sa</a>
        @endif

        <div class="warning">
            <strong>Odporúčame</strong> zmeniť heslo ihneď po prvom prihlásení.
        </div>

        <x-email.contact-block />
    </div>
    <div class="footer">
        Táto správa bola odoslaná automaticky, neodpovedajte na ňu.
    </div>
</div>
</body>
</html>
