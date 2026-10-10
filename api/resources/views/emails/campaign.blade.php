<!doctype html>
<html lang="sk"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>
<body style="margin:0;background:#f1f5f9;font-family:Arial,sans-serif;color:#163047">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:32px 12px">
<table role="presentation" width="600" style="max-width:600px;width:100%;background:white;border-radius:16px;overflow:hidden" cellpadding="0" cellspacing="0">
@if($unsubscribe)<tr><td align="right" style="padding:12px 32px;background:#f8fafc;font-size:12px"><a href="{{ $unsubscribe }}" style="color:#64748b">Zrušiť odber</a></td></tr>@endif
<tr><td style="padding:28px 32px;background:#113c42;color:white;font-weight:bold;letter-spacing:2px">ZÁSTAVY &amp; VLAJKY</td></tr>
<tr><td style="padding:36px 32px">
<p style="color:#628078;font-size:13px">PONUKA PRE NAŠICH ZÁKAZNÍKOV</p>
<h1 style="font-size:30px;line-height:1.2">{{ $campaign->heading }}</h1>
@if($contact && $contact->name)<p>Dobrý deň, {{ $contact->name }},</p>@endif
<div style="font-size:16px;line-height:1.8">{!! \App\Services\EmailingService::bodyHtml($campaign->body, 'margin:0 0 16px') !!}</div>
@if($campaign->coupon_code ?? null)<div style="margin:24px 0;padding:22px;background:#ecf8f1;border:1px dashed #57a389;text-align:center">Váš zľavový kód<br><strong style="font-size:26px;letter-spacing:3px">{{ $campaign->coupon_code }}</strong><br><small>Platnosť a podmienky kupóna sa overia v košíku.</small></div>@endif
@if($campaign->button_url)<p style="margin-top:30px"><a href="{{ $campaign->button_url }}" style="display:inline-block;padding:15px 24px;background:#117866;color:white;border-radius:8px;text-decoration:none">{{ $campaign->button_label ?: 'Pozrieť ponuku' }}</a></p>@endif
</td></tr>
<tr><td style="padding:24px 32px;background:#f8fafc;color:#64748b;font-size:12px;line-height:1.7">{{ config('mail.from.name') }}<br>{{ config('mail.from.address') }}<br>
@if($unsubscribe)<a href="{{ $unsubscribe }}" style="color:#64748b">Odhlásiť sa z emailových ponúk</a>@else Náhľad / test – odhlásenie bude dostupné v kampani.@endif
</td></tr></table></td></tr></table></body></html>
