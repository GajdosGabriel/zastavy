@props(['item'])

{{-- Bunka „Tovar“ v e-mailoch: náhľad ako v košíku + názov a variant.
     Vnorená tabuľka namiesto flexu — Outlook flex nepozná. --}}
<table role="presentation" style="width:auto; margin:0; border-collapse:collapse;">
    <tr>
        @if($src = $item->email_image_url)
        <td style="padding:0 12px 0 0; border-bottom:none; vertical-align:middle; width:48px;">
            <img src="{{ $src }}" alt="" width="48" height="48"
                 style="display:block; width:48px; height:48px; object-fit:cover; border:1px solid #e5e7eb; border-radius:6px;">
        </td>
        @endif
        <td style="padding:0; border-bottom:none; vertical-align:middle; font-size:14px;">
            {{ $item->product_details->name ?? '—' }}
            @if($item->variant_name)
                <br><span style="color:#64748b;font-size:12px">{{ $item->variant_name }}</span>
            @endif
        </td>
    </tr>
</table>
