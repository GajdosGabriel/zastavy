@props(['title', 'heading', 'subheading' => null, 'accent' => '#1e3a5f', 'contact' => true])
{{-- Spoločná kostra všetkých transakčných e-mailov: hlavička, telo, kontakt a pätička.
     Každá šablóna dodá len obsah (a voliteľne vlastné štýly cez slot `styles`),
     takže potvrdenie, expedícia, storno aj kupón vyzerajú rovnako.
     `accent` = farba hlavičky (storno červená, vrátenie hnedá). --}}
<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    <style>
        body { font-family: Arial, sans-serif; color: #333; margin: 0; padding: 0; background: #f5f5f5; }
        .wrapper { max-width: 600px; margin: 30px auto; background: #fff; border-radius: 6px; overflow: hidden; }
        .header { color: #fff; padding: 28px 32px; }
        .header h1 { margin: 0; font-size: 22px; font-weight: 600; }
        .header p { margin: 6px 0 0; font-size: 14px; color: #e2e8f0; }
        .body { padding: 32px; }
        .lead { font-size: 15px; margin: 0 0 24px; line-height: 1.6; }
        .muted { font-size: 13px; color: #555; margin: 0 0 24px; line-height: 1.5; }
        .section-title { font-size: 12px; font-weight: 700; text-transform: uppercase; color: #555; letter-spacing: 0.5px; margin: 0 0 10px; }
        .info-block { margin-bottom: 24px; }
        .info-block p { margin: 4px 0; font-size: 14px; }
        .info-block p.section-title { font-size: 12px; margin: 0 0 10px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        th { text-align: left; font-size: 12px; text-transform: uppercase; color: #555; padding: 6px 0; border-bottom: 1px solid #e5e5e5; }
        td { padding: 9px 0; font-size: 14px; border-bottom: 1px solid #f0f0f0; vertical-align: top; }
        .total-row td { font-weight: 700; font-size: 15px; border-bottom: none; padding-top: 14px; }
        .sent-box { background: #f0fdf4; border: 1px solid #86efac; border-radius: 5px; padding: 16px 20px; margin-bottom: 24px; }
        .sent-box .box-title { font-size: 13px; font-weight: 700; color: #166534; margin-bottom: 10px; }
        .sent-box table { margin-bottom: 0; }
        .sent-box th { color: #15803d; border-bottom-color: #86efac; }
        .sent-box td { color: #166534; border-bottom-color: #dcfce7; }
        .pending-box { background: #fffbeb; border: 1px solid #fcd34d; border-radius: 5px; padding: 16px 20px; margin-bottom: 24px; }
        .pending-box .box-title { font-size: 13px; font-weight: 700; color: #92400e; margin-bottom: 10px; }
        .pending-box table { margin-bottom: 0; }
        .pending-box th { color: #a16207; border-bottom-color: #fcd34d; }
        .pending-box td { font-size: 13px; color: #78350f; border-bottom-color: #fde68a; }
        .pending-box p { margin: 10px 0 0; font-size: 13px; color: #78350f; }
        .note-box { background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 5px; padding: 14px 18px; margin-bottom: 24px; font-size: 13px; color: #1e3a5f; }
        .warn-box { background: #fffbeb; border: 1px solid #fcd34d; border-radius: 5px; padding: 14px 18px; margin-bottom: 24px; font-size: 13px; color: #78350f; }
        .shipping-line { font-size: 14px; color: #555; margin-bottom: 24px; }
        .shipping-line strong { color: #1e3a5f; }
        .button-row { margin: 0 0 24px; text-align: center; }
        .button { display: inline-block; background: #1e3a5f; color: #fff !important; text-decoration: none; padding: 12px 28px; border-radius: 5px; font-size: 14px; font-weight: 600; }
        .footer { background: #f9f9f9; padding: 20px 32px; font-size: 12px; color: #666; text-align: center; border-top: 1px solid #eee; line-height: 1.5; }
        @media only screen and (max-width: 520px) {
            .body { padding: 24px 20px !important; }
            .header { padding: 22px 20px !important; }
            .cta-text, .cta-action { display: block !important; width: auto !important; text-align: left !important; }
            .cta-text { padding-bottom: 10px !important; }
            .cta-action { padding: 0 18px 14px !important; white-space: normal !important; }
        }
    </style>
    {{ $styles ?? '' }}
</head>
<body>
<div class="wrapper">
    <div class="header" style="background: {{ $accent }};">
        <h1>{{ $heading }}</h1>
        @if($subheading)
            <p>{{ $subheading }}</p>
        @endif
    </div>

    <div class="body">
        {{ $slot }}

        @if($contact)
            <x-email.contact-block />
        @endif
    </div>

    <div class="footer">
        Gajdoš Gabriel – Reprezent · Slatinská 14, 921 07 Bratislava · zastavy-vlajky.sk
    </div>
</div>
</body>
</html>
