@props(['order'])
{{-- Údaje na úhradu prevodom. Nič sa nezobrazí, ak sa neplatí prevodom
     alebo obchod nemá v .env vyplnený IBAN (Order::paymentInstructions). --}}
@php($payment = $order->paymentInstructions())
@if($payment)
<div style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:5px; padding:16px 20px; margin-bottom:24px;">
    <p style="margin:0 0 4px; font-size:15px; font-weight:700; color:#1e3a5f;">Platba prevodom na účet</p>
    <p style="margin:0 0 12px; font-size:13px; color:#1e3a5f;">Objednávku vybavíme po prijatí platby. Pri úhrade uveďte variabilný symbol.</p>
    <table role="presentation" style="margin:0;">
        <tr>
            <td style="padding:4px 12px 4px 0; border-bottom:none; font-size:13px; color:#555; white-space:nowrap;">IBAN</td>
            <td style="padding:4px 0; border-bottom:none; font-size:15px; font-weight:700; font-family:monospace; color:#111;">{{ trim(chunk_split(preg_replace('/\s+/', '', $payment['iban']), 4, ' ')) }}</td>
        </tr>
        <tr>
            <td style="padding:4px 12px 4px 0; border-bottom:none; font-size:13px; color:#555; white-space:nowrap;">Variabilný symbol</td>
            <td style="padding:4px 0; border-bottom:none; font-size:15px; font-weight:700; font-family:monospace; color:#111;">{{ $payment['variable_symbol'] }}</td>
        </tr>
        <tr>
            <td style="padding:4px 12px 4px 0; border-bottom:none; font-size:13px; color:#555; white-space:nowrap;">Suma</td>
            <td style="padding:4px 0; border-bottom:none; font-size:15px; font-weight:700; color:#111;">{{ number_format($payment['amount'], 2, ',', ' ') }} €</td>
        </tr>
        @if($payment['holder'])
        <tr>
            <td style="padding:4px 12px 4px 0; border-bottom:none; font-size:13px; color:#555; white-space:nowrap;">Príjemca</td>
            <td style="padding:4px 0; border-bottom:none; font-size:14px; color:#111;">{{ $payment['holder'] }}</td>
        </tr>
        @endif
        @if($payment['swift'])
        <tr>
            <td style="padding:4px 12px 4px 0; border-bottom:none; font-size:13px; color:#555; white-space:nowrap;">SWIFT / BIC</td>
            <td style="padding:4px 0; border-bottom:none; font-size:14px; font-family:monospace; color:#111;">{{ $payment['swift'] }}</td>
        </tr>
        @endif
    </table>
</div>
@endif
