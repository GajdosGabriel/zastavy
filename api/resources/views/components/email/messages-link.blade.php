@props(['order'])
{{-- Zákazník môže k objednávke napísať sám — odpoveď sa uloží ku konverzácii
     objednávky, nie do poštovej schránky. --}}
@if($order->exists && ! $order->trashed())
<table role="presentation" style="margin:0 0 24px; background:#eff6ff; border:1px solid #bfdbfe; border-radius:5px;">
    <tr>
        <td class="cta-text" style="padding:14px 18px; border-bottom:none; font-size:13px; color:#1e3a5f;">
            <strong style="display:block; font-size:14px; margin-bottom:3px;">Chcete niečo doplniť alebo zmeniť?</strong>
            Napíšte nám priamo k objednávke, môžete priložiť aj súbor.
        </td>
        <td class="cta-action" style="padding:14px 18px 14px 0; border-bottom:none; text-align:right; white-space:nowrap; vertical-align:middle;">
            <a href="{{ $order->messagesUrl() }}"
               style="display:inline-block; background:#fff; color:#1e3a5f; border:1px solid #1e3a5f; text-decoration:none; padding:8px 16px; border-radius:5px; font-size:13px; font-weight:600;">
                Napísať k objednávke
            </a>
        </td>
    </tr>
</table>
@endif
