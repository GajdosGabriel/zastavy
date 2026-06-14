<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Obnovenie hesla</title>
    <style>
        body { font-family: Arial, sans-serif; color: #333; margin: 0; padding: 0; background: #f5f5f5; }
        .wrapper { max-width: 560px; margin: 30px auto; background: #fff; border-radius: 6px; overflow: hidden; }
        .header { background: #1e3a5f; color: #fff; padding: 28px 32px; }
        .header h1 { margin: 0; font-size: 22px; font-weight: 600; }
        .header p { margin: 6px 0 0; font-size: 14px; color: #aaa; }
        .body { padding: 32px; }
        .body p { font-size: 14px; line-height: 1.6; margin: 0 0 16px; }
        .btn { display: inline-block; margin: 8px 0 24px; padding: 12px 28px; background: #2563eb; color: #fff; text-decoration: none; border-radius: 5px; font-size: 15px; font-weight: 600; }
        .btn:hover { background: #1d4ed8; }
        .url-fallback { font-size: 12px; color: #888; word-break: break-all; }
        .footer { background: #f9f9f9; padding: 18px 32px; font-size: 12px; color: #aaa; text-align: center; border-top: 1px solid #eee; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="header">
        <h1>Obnovenie hesla</h1>
        <p>Žiadosť o reset hesla</p>
    </div>
    <div class="body">
        <p>Dobrý deň,</p>
        <p>dostali sme požiadavku na obnovenie hesla k Vášmu účtu. Kliknite na tlačidlo nižšie a nastavte si nové heslo.</p>

        <a href="{{ $url }}" class="btn">Nastaviť nové heslo</a>

        <p>Platnosť odkazu vyprší o <strong>60 minút</strong>.</p>
        <p>Ak ste o obnovenie hesla nežiadali, tento email ignorujte — Vaše heslo zostane nezmenené.</p>

        <p class="url-fallback">Ak tlačidlo nefunguje, skopírujte tento odkaz do prehliadača:<br>{{ $url }}</p>

        <x-email.contact-block />
    </div>
    <div class="footer">
        Táto správa bola vygenerovaná automaticky
    </div>
</div>
</body>
</html>
