<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Váš zľavový kupón</title>
    <style>
        body { font-family: Arial, sans-serif; color: #333; margin: 0; padding: 0; background: #f5f5f5; }
        .wrapper { max-width: 600px; margin: 30px auto; background: #fff; border-radius: 6px; overflow: hidden; }
        .header { background: #1e3a5f; color: #fff; padding: 28px 32px; }
        .header h1 { margin: 0; font-size: 22px; font-weight: 600; }
        .header p { margin: 6px 0 0; font-size: 14px; color: #aaa; }
        .body { padding: 32px; }
        .section-title { font-size: 13px; font-weight: 700; text-transform: uppercase; color: #888; letter-spacing: 0.5px; margin: 0 0 12px; }
        .info-block { margin-bottom: 28px; }
        .info-block p { margin: 4px 0; font-size: 14px; }
        .coupon-box { background: #f0fdf4; border: 2px dashed #22c55e; border-radius: 8px; padding: 24px 32px; text-align: center; margin: 28px 0; }
        .coupon-code { font-size: 32px; font-weight: 700; letter-spacing: 6px; color: #15803d; font-family: monospace; margin: 0 0 8px; }
        .coupon-badge { display: inline-block; background: #22c55e; color: #fff; font-size: 13px; font-weight: 600; border-radius: 20px; padding: 4px 16px; margin-bottom: 16px; }
        .coupon-meta { font-size: 13px; color: #555; line-height: 1.8; }
        .coupon-meta strong { color: #333; }
        .alert-box { background: #fff7ed; border: 1px solid #fed7aa; border-radius: 5px; padding: 14px 18px; margin-bottom: 24px; font-size: 13px; color: #9a3412; }
        .footer { background: #f9f9f9; padding: 20px 32px; font-size: 12px; color: #aaa; text-align: center; border-top: 1px solid #eee; }
        .btn { display: inline-block; background: #1e3a5f; color: #fff; text-decoration: none; padding: 12px 28px; border-radius: 5px; font-size: 14px; font-weight: 600; margin-top: 20px; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="header">
        <h1>Zľavový kupón pre Vás</h1>
        <p>Ďakujeme za Vašu objednávku č. {{ $order->serial_number }}</p>
    </div>

    <div class="body">

        <div class="info-block">
            <p>Dobrý deň,</p>
            <p style="margin-top:10px;">
                ako sme sľúbili, posielame Vám zľavový kupón na ďalší nákup.
                Kupón je jednorazový, platí pre objednávky splňujúce podmienky uvedené nižšie.
            </p>
        </div>

        <div class="coupon-box">
            <div class="coupon-badge">{{ $coupon->value }}% zľava</div>
            <div class="coupon-code">{{ $coupon->code }}</div>
            <div class="coupon-meta">
                <strong>Platí od:</strong> {{ $coupon->valid_from->format('d.m.Y') }}<br>
                <strong>Platí do:</strong> {{ $coupon->valid_to->format('d.m.Y') }}<br>
                <strong>Minimálna objednávka:</strong> {{ number_format($coupon->min_order_price, 0, ',', ' ') }} €<br>
                <strong>Jednorazový</strong> — kupón môžete použiť iba raz
            </div>
        </div>

        <div class="alert-box">
            Kupón <strong>nie je možné kombinovať</strong> s inými akciami alebo zľavami.
            Platí výhradne pre objednávky zadané na <strong>zastavy-vlajky.sk</strong>.
        </div>

        <p class="section-title">Ako uplatniť kupón?</p>
        <div class="info-block">
            <p>1. Vložte tovar do košíka na našom webe</p>
            <p>2. V sekcii <strong>„Mám kupón"</strong> zadajte kód kupóna</p>
            <p>3. Kliknite na <strong>„Uplatniť"</strong> — zľava sa automaticky odpočíta</p>
        </div>

        <div style="text-align:center;">
            <a href="{{ env('FRONTEND_URL', config('app.url')) }}" class="btn">
                Nakupovať teraz
            </a>
        </div>

        <x-email.contact-block />
    </div>

    <div class="footer">
        Gajdoš Gabriel – Reprezent · Slatinská 14, 921 07 Bratislava
    </div>
</div>
</body>
</html>
