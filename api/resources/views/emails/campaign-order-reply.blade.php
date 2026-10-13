<!doctype html>
<html lang="sk"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
@if($quoteStyle ?? null)<style>{!! $quoteStyle !!}</style>@endif
</head>
{{-- Odpoveď na systémový e-mail objednávky: text a pod ním citovaná pôvodná správa, ako pri ručnej
     odpovedi z poštového klienta. Triedu gmail_quote klienti poznajú ako citát a vedia ho zbaliť. --}}
<body style="margin:0;padding:0;font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#222">
<div dir="ltr">
{!! \App\Services\EmailingService::orderReplyBody($campaign->body, $order) !!}
</div>
@if($quoteHtml ?? null)
<br>
<div class="gmail_quote">
<div dir="ltr" class="gmail_attr">{{ $quotedAt->locale('sk')->isoFormat('dd D. M. YYYY [o] H:mm') }} {{ \App\Support\OrderThread::FROM_NAME }} &lt;<a href="mailto:{{ \App\Support\OrderThread::FROM }}">{{ \App\Support\OrderThread::FROM }}</a>&gt; napísal(a):<br></div>
<blockquote class="gmail_quote" style="margin:0 0 0 .8ex;border-left:1px solid #ccc;padding-left:1ex">
{!! $quoteHtml !!}
</blockquote>
</div>
@elseif($campaign->quote_event ?? true)
<p style="margin:24px 0 0;font-size:13px;color:#666">Náhľad – pod textom bude citovaný e-mail, na ktorý sa odpovedá.</p>
@endif
</body></html>
