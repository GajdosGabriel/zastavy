<!doctype html>
<html lang="sk"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>
<body style="margin:0;background:#f1f5f9">
<div style="display: none; font-size: 1px; color: #f1f5f9; max-height: 0; overflow: hidden;">Menej ručného začierňovania. Anonymizer nájdete na novej adrese spisor.eu.</div>
<table style="background: #f1f5f9; font-family: Arial,Helvetica,sans-serif; color: #172033;" role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td style="padding: 28px 12px;" align="center">
<table style="width: 100%; max-width: 600px; background: #ffffff;" role="presentation" border="0" width="600" cellspacing="0" cellpadding="0">
<tbody>
@if($unsubscribe)<tr><td align="right" style="padding:12px 32px;background:#f8fafc;font-size:12px"><a href="{{ $unsubscribe }}" style="color:#64748b">Zrušiť odber</a></td></tr>@endif
<tr>
<td style="padding: 26px 32px; background: #101b35; color: #ffffff; font-size: 23px; font-weight: bold;"><span style="color: rgb(255, 255, 255); font-size: 23px; font-weight: bold;">Spisor.eu  </span><span style="color: #8fa6cc;"><span style="font-size: 13px;">citlivé údaje zmiznú, dokument zostane.</span></span></td>
</tr>
<tr>
<td style="padding: 32px; background: #101b35; color: #ffffff;">
<p style="margin: 0 0 16px; font-size: 12px; letter-spacing: 2px; color: #67e8f9;">DOKUMENTY POD VAŠOU KONTROLOU</p>
<h1 style="margin: 0 0 20px; font-size: 36px; line-height: 1.18; color: #ffffff;">@if($campaign->heading === "Citlivé údaje zmiznú. Dokument zostane.")Citlivé údaje zmiznú.<br><span style="color:#67e8f9">Dokument zostane.</span>@else{{ $campaign->heading }}@endif</h1>
<p style="margin: 0; font-size: 17px; line-height: 1.65; color: #dce5f5;">Pripravte zmluvy, podklady a ďalšie dokumenty na zdieľanie. S menším množstvom ručného začierňovania.</p>
</td>
</tr>
<tr>
<td style="padding: 30px 32px 12px; font-size: 16px; line-height: 1.7;">
<p style="margin: 0 0 16px;">Dobrý deň@if($contact && $contact->name), {{ $contact->name }}@endif,</p>
{!! \App\Services\EmailingService::bodyHtml($campaign->body, 'margin: 0 0 18px;') !!}
@if($campaign->coupon_code ?? null)<p style="padding:18px;background:#eff6ff;text-align:center">Váš zľavový kód: <strong>{{ $campaign->coupon_code }}</strong><br><small>Platnosť a podmienky kupóna sa overia v košíku.</small></p>@endif
</td>
</tr>
<tr>
<td style="padding: 12px 32px 24px;">
<table role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td style="padding: 16px 18px; background: #eff6ff; font-size: 15px; line-height: 1.6; border-bottom: 5px solid #ffffff;"><strong style="color: #175cd3;">01   Menej ručnej práce</strong><br>Mená, rodné čísla, IBAN, e-maily, telefóny aj adresy nájde automaticky — v PDF, Worde, Exceli aj v naskenovaných dokumentoch cez OCR.</td>
</tr>
<tr>
<td style="padding: 16px 18px; background: #eff6ff; font-size: 15px; line-height: 1.6; border-bottom: 5px solid #ffffff;"><strong style="color: #175cd3;">02   Vy máte posledné slovo</strong><br>Pred finálnym začiernením si prejdete zoznam nálezov a odškrtnete falošné poplachy. Začierni sa len to, čo potvrdíte.</td>
</tr>
<tr>
<td style="padding: 16px 18px; background: #eff6ff; font-size: 15px; line-height: 1.6;"><strong style="color: #175cd3;">03   Nezvratná redakcia</strong><br>Schválené údaje sa z dokumentu skutočne odstránia — neprekryjú sa len čiernym obdĺžnikom, ktorý sa dá zrušiť.</td>
</tr>
</tbody>
</table>
</td>
</tr>
<tr>
<td style="padding: 10px 32px 30px;" align="center">
<h2 style="margin: 0 0 14px; font-size: 23px; line-height: 1.35;">Od dokumentu k výsledku v 3 krokoch</h2>
<p style="margin: 0 0 26px; font-size: 15px; line-height: 1.7; color: #526079;">Nahrajte súbor. Skontrolujte nálezy.<br>Stiahnite anonymizovaný dokument.</p>
@if($campaign->button_url)
<table role="presentation" border="0" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td style="border-radius: 7px;" align="center" bgcolor="#2563eb"><a style="display: inline-block; padding: 17px 29px; border: 1px solid #2563eb; border-radius: 7px; color: #ffffff; font-size: 17px; font-weight: bold; text-decoration: none;" href="{{ $campaign->button_url }}" target="_blank" rel="noopener">{{ $campaign->button_label ?: "Vyskúšať Anonymizer →" }}</a></td>
</tr>
</tbody>
</table>
@endif
<p style="margin: 16px 0 0; font-size: 13px; line-height: 1.5; color: #64748b;">Interaktívna ukážka aj minútové video priamo na stránke — bez registrácie.<br><a style="color: #475569;" href="https://spisor.eu" target="_blank" rel="noopener">spisor.eu</a></p>
</td>
</tr>
<tr>
<td style="padding: 21px 32px; background: #eaf0f8; font-size: 14px; line-height: 1.7; color: #475569;"><strong style="color: #172033;">Pre jednotlivcov aj tímy</strong><br>Dávkové spracovanie, vlastné vzory, zdieľané roly, schvaľovanie druhou osobou a audit log pomáhajú zvládnuť aj pravidelnú prácu s citlivými podkladmi.</td>
</tr>
<tr>
<td style="padding: 24px 32px; font-size: 12px; line-height: 1.7; color: #64748b;"><br>
<p style="margin: 16px 0 0;">{{ config("mail.from.name") }}<br>{{ config("mail.from.address") }}<br>Táto správa je obchodná ponuka.@if($contact) Bola odoslaná na {{ $contact->email }}.@endif<br>
@if($unsubscribe)Ak si neželáte dostávať ďalšie ponuky: <a style="color:#64748b" href="{{ $unsubscribe }}">Odhlásiť sa z odberu</a>.@else Náhľad / test – odhlásenie bude dostupné v kampani.@endif</p>
</td>
</tr>
</tbody>
</table>
</td>
</tr>
</tbody>
</table>

</body></html>
