<!doctype html>
<html lang="sk"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><style>@media screen and (max-width:480px){.product-column{display:block!important;width:100%!important;box-sizing:border-box}}</style></head>
<body style="margin:0;background:#f3f5f8">
<div style="display: none; max-height: 0; overflow: hidden; font-size: 1px; color: #f3f5f8;">Vlajky SR a EÚ za 23 € s DPH. Objavte aj štátne symboly do tried a znak na stenu.</div>
<table style="background: #f3f5f8; font-family: Arial,Helvetica,sans-serif; color: #26354a;" role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td style="padding: 24px 10px;" align="center">
<table style="width: 100%; max-width: 620px; background: #ffffff;" role="presentation" border="0" width="620" cellspacing="0" cellpadding="0">
<tbody>
<tr>
@if($unsubscribe)<td align="right" style="padding:12px 30px;background:#f3f5f8;font-size:12px"><a href="{{ $unsubscribe }}" style="color:#52647d">Zrušiť odber</a></td></tr><tr>@endif
<td style="padding: 22px 30px; font-size: 21px; font-weight: bold; color: #173f78;">ZÁSTAVY <span style="color: #d42b36;">A</span> VLAJKY<span style="display: block; margin-top: 5px; font-size: 11px; font-weight: normal; letter-spacing: 1px; color: #697586;">PRE ŠKOLY, OBCE A ORGANIZÁCIE</span></td>
</tr>
<tr>
<td style="height: 5px; background: #d42b36; font-size: 1px; line-height: 5px;"> </td>
</tr>
<tr>
<td style="padding: 32px 30px; background: #173f78; color: #ffffff;">
<p style="margin: 0 0 14px; font-size: 11px; letter-spacing: 2px; color: #c5d8f2;">NECH VAŠA BUDOVA REPREZENTUJE</p>
<h1 style="margin: 0 0 18px; font-size: 34px; line-height: 1.2; color: #ffffff;">@if($campaign->heading === "Nová vlajka. Dôstojný prvý dojem.")Nová vlajka.<br>Dôstojný prvý dojem.@else{{ $campaign->heading }}@endif</h1>
<p style="margin: 0; font-size: 16px; line-height: 1.7; color: #e3ecf8;">Obnovte vlajky pred budovou a doplňte štátne symboly do tried či kancelárií. Vyberte si všetko pohodlne na jednom mieste.</p>
</td>
</tr>
<tr>
<td style="padding: 26px 30px 12px; font-size: 15px; line-height: 1.7;">
<p style="margin: 0 0 12px;">Dobrý deň@if($contact && $contact->name), {{ $contact->name }}@endif,</p>
@foreach(preg_split("/\r?\n\s*\r?\n/", $campaign->body) as $paragraph)
<p style="margin: 0 0 12px;">{!! nl2br(e($paragraph)) !!}</p>
@endforeach
@if($campaign->coupon_code ?? null)<p style="padding:18px;background:#f0f4fa;text-align:center">Váš zľavový kód: <strong>{{ $campaign->coupon_code }}</strong><br><small>Platnosť a podmienky kupóna sa overia v košíku.</small></p>@endif
</td>
</tr>
<tr>
<td style="padding: 12px 20px 20px;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td class="product-column" style="width: 50%; padding: 10px;" valign="top" width="50%">
<table style="border: 1px solid #e2e8f0; background: #ffffff;" role="presentation" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td style="padding: 16px; height: 180px;" align="center" height="180"><a href="https://zastavy-vlajky.sk/product/1/show/vlajka-slovenskej-republiky">@if(isset($productImages[1]))<img style="display: block; height: 160px; width: auto; max-width: 100%; border: 0;" src="{{ $productImages[1] }}" alt="Vlajka Slovenskej republiky" height="160">@else<span style="color:#697586;font-size:13px">Obrázok produktu nie je dostupný</span>@endif</a></td>
</tr>
<tr>
<td style="padding: 0 18px 20px;">
<h3 style="margin: 0 0 10px; font-size: 18px; line-height: 1.35; color: #172b4d;">Vlajka Slovenskej republiky</h3>
<p style="margin: 0 0 14px; font-size: 13px; line-height: 1.6; color: #5a687c;">100 × 150 cm · PES materiál<br>Na použitie vo vonkajšom prostredí.</p>
<p style="margin: 0 0 15px; color: #172b4d;"><strong style="font-size: 25px;">23,00 €</strong> <span style="font-size: 12px;">s DPH / ks</span></p>
<a style="display: inline-block; background: #173f78; border: 10px solid #173f78; border-left-width: 14px; border-right-width: 14px; color: #ffffff; text-decoration: none; font-size: 13px; font-weight: bold;" href="https://zastavy-vlajky.sk/product/1/show/vlajka-slovenskej-republiky">Pozrieť produkt →</a></td>
</tr>
</tbody>
</table>
</td>
<td class="product-column" style="width: 50%; padding: 10px;" valign="top" width="50%">
<table style="border: 1px solid #e2e8f0; background: #ffffff;" role="presentation" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td style="padding: 16px; height: 180px;" align="center" height="180"><a href="https://zastavy-vlajky.sk/product/2/show/vlajka-europskej-unie">@if(isset($productImages[2]))<img style="display: block; height: 160px; width: auto; max-width: 100%; border: 0;" src="{{ $productImages[2] }}" alt="Vlajka Európskej únie" height="160">@else<span style="color:#697586;font-size:13px">Obrázok produktu nie je dostupný</span>@endif</a></td>
</tr>
<tr>
<td style="padding: 0 18px 20px;">
<h3 style="margin: 0 0 10px; font-size: 18px; line-height: 1.35; color: #172b4d;">Vlajka Európskej únie</h3>
<p style="margin: 0 0 14px; font-size: 13px; line-height: 1.6; color: #5a687c;">100 × 150 cm · PES materiál<br>Pre budovy a vonkajšie priestory.</p>
<p style="margin: 0 0 15px; color: #172b4d;"><strong style="font-size: 25px;">23,00 €</strong> <span style="font-size: 12px;">s DPH / ks</span></p>
<a style="display: inline-block; background: #173f78; border: 10px solid #173f78; border-left-width: 14px; border-right-width: 14px; color: #ffffff; text-decoration: none; font-size: 13px; font-weight: bold;" href="https://zastavy-vlajky.sk/product/2/show/vlajka-europskej-unie">Pozrieť produkt →</a></td>
</tr>
</tbody>
</table>
</td>
</tr>
<tr>
<td class="product-column" style="width: 50%; padding: 10px;" valign="top" width="50%">
<table style="border: 1px solid #e2e8f0; background: #ffffff;" role="presentation" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td style="padding: 16px; height: 180px;" align="center" height="180"><a href="https://zastavy-vlajky.sk/product/5/show/statne-symboly-do-tried">@if(isset($productImages[5]))<img style="display: block; height: 160px; width: auto; max-width: 100%; border: 0;" src="{{ $productImages[5] }}" alt="Štátne symboly do tried" height="160">@else<span style="color:#697586;font-size:13px">Obrázok produktu nie je dostupný</span>@endif</a></td>
</tr>
<tr>
<td style="padding: 0 18px 20px;">
<h3 style="margin: 0 0 10px; font-size: 18px; line-height: 1.35; color: #172b4d;">Štátne symboly do tried</h3>
<p style="margin: 0 0 14px; font-size: 13px; line-height: 1.6; color: #5a687c;">100 × 70 cm · sada s lištami<br>Vlajka, hymna a preambula ústavy.</p>
<p style="margin: 0 0 15px; color: #172b4d;"><strong style="font-size: 25px;">29,00 €</strong> <span style="font-size: 12px;">s DPH / ks</span></p>
<a style="display: inline-block; background: #173f78; border: 10px solid #173f78; border-left-width: 14px; border-right-width: 14px; color: #ffffff; text-decoration: none; font-size: 13px; font-weight: bold;" href="https://zastavy-vlajky.sk/product/5/show/statne-symboly-do-tried">Pozrieť produkt →</a></td>
</tr>
</tbody>
</table>
</td>
<td class="product-column" style="width: 50%; padding: 10px;" valign="top" width="50%">
<table style="border: 1px solid #e2e8f0; background: #ffffff;" role="presentation" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td style="padding: 16px; height: 180px;" align="center" height="180"><a href="https://zastavy-vlajky.sk/product/6/show/statny-znak-sr-na-stenu">@if(isset($productImages[6]))<img style="display: block; height: 160px; width: auto; max-width: 100%; border: 0;" src="{{ $productImages[6] }}" alt="Štátny znak SR na stenu" height="160">@else<span style="color:#697586;font-size:13px">Obrázok produktu nie je dostupný</span>@endif</a></td>
</tr>
<tr>
<td style="padding: 0 18px 20px;">
<h3 style="margin: 0 0 10px; font-size: 18px; line-height: 1.35; color: #172b4d;">Štátny znak SR na stenu</h3>
<p style="margin: 0 0 14px; font-size: 13px; line-height: 1.6; color: #5a687c;">33 × 27 cm · biely drevený rám<br>So sklom a úchytkou na zavesenie.</p>
<p style="margin: 0 0 15px; color: #172b4d;"><strong style="font-size: 25px;">14,68 €</strong> <span style="font-size: 12px;">s DPH / ks</span></p>
<a style="display: inline-block; background: #173f78; border: 10px solid #173f78; border-left-width: 14px; border-right-width: 14px; color: #ffffff; text-decoration: none; font-size: 13px; font-weight: bold;" href="https://zastavy-vlajky.sk/product/6/show/statny-znak-sr-na-stenu">Pozrieť produkt →</a></td>
</tr>
</tbody>
</table>
</td>
</tr>
</tbody>
</table>
</td>
</tr>
<tr>
<td style="padding: 0 30px 28px;">
<table style="background: #f0f4fa;" role="presentation" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td style="padding: 21px; font-size: 14px; line-height: 1.7;"><strong style="font-size: 18px; color: #173f78;">Hľadáte zástavu vašej obce?</strong><br>V ponuke nájdete aj obecné, mestské a školské zástavy podľa dodanej predlohy.<br><a style="color: #173f78; font-weight: bold;" href="https://zastavy-vlajky.sk/product/7/show/obecne-zastavy">Pozrieť obecné zástavy →</a></td>
</tr>
</tbody>
</table>
</td>
</tr>
<tr>
<td style="padding: 0 30px 30px;" align="center">
@if($campaign->button_url)
<table role="presentation" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td style="border-radius: 5px;" bgcolor="#d42b36"><a style="display: inline-block; padding: 16px 28px; border: 1px solid #d42b36; color: #ffffff; text-decoration: none; font-size: 16px; font-weight: bold;" href="{{ $campaign->button_url }}">{{ $campaign->button_label ?: "Prezrieť celú ponuku →" }}</a></td>
</tr>
</tbody>
</table>
@endif
<p style="margin: 18px 0 0; font-size: 14px; line-height: 1.7;">Potrebujete poradiť s výberom?<br><a style="color: #173f78; font-weight: bold; text-decoration: none;" href="tel:+421905320616">0905 320 616</a>  |  <a style="color: #173f78;" href="mailto:obchod@zastavy-vlajky.sk">obchod@zastavy-vlajky.sk</a></p>
</td>
</tr>
<tr>
<td style="padding: 22px 30px; border-top: 1px solid #e2e8f0; font-size: 12px; line-height: 1.8; color: #6b7280;">Gajdoš Gabriel – Reprezent<br>Slatinská 14, Bratislava<br><a style="color: #52647d;" href="https://zastavy-vlajky.sk/">www.zastavy-vlajky.sk</a>
<p style="margin: 14px 0 0;">Táto správa je obchodná ponuka.@if($contact) Bola odoslaná na {{ $contact->email }}.@endif<br>
@if($unsubscribe)Ak si neželáte dostávať ďalšie ponuky: <a style="color:#52647d" href="{{ $unsubscribe }}">Odhlásiť sa z odberu</a>.@else Náhľad / test – odhlásenie bude dostupné v kampani.@endif</p>
</td>
</tr>
</tbody>
</table>
</td>
</tr>
</tbody>
</table>

</body></html>
