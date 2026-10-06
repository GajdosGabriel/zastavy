<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class EmailingMeasurement
{
    public static function safeDestination(string $url): bool
    {
        $parts = parse_url($url);
        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && ($parts['scheme'] ?? '') === 'https' && ! isset($parts['user']) && ! isset($parts['pass'])
            && ! preg_match('/[\x00-\x20\x7f]/', $url);
    }

    public function decorate(string $html, object $campaign, object $delivery, string $unsubscribe): string
    {
        if ($campaign->track_clicks) {
            $position = 0;
            // Only quoted hrefs in our server-rendered templates; preserve the
            // email's original markup (including Outlook-compatible tables).
            $html = preg_replace_callback('~<a\b([^>]*?)\bhref=(["\x27])(.*?)\2([^>]*)>(.*?)</a>~is', function ($m) use ($delivery, $unsubscribe, &$position) {
                $url = html_entity_decode($m[3], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $label = trim(html_entity_decode(strip_tags($m[5]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                // Never wrap unsubscribe, telephone/email links or visible URL
                // text: the latter should keep matching the actual destination.
                if ($url === $unsubscribe || ! self::safeDestination($url)
                    || preg_match('~^(?:https?://|www\.)~i', $label)
                    || preg_match('~^[a-z0-9.-]+\.[a-z]{2,}(?:/\S*)?$~i', $label)) return $m[0];
                if ($label === '' && preg_match('~\balt=(["\x27])(.*?)\1~is', $m[5], $alt)) {
                    $label = html_entity_decode($alt[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                }
                $link = DB::table('mailing_links')->where('delivery_id', $delivery->id)->where('position', ++$position)->first();
                if (! $link) {
                    $token = Str::random(64);
                    DB::table('mailing_links')->insert([
                        'delivery_id' => $delivery->id, 'position' => $position,
                        'token' => $token, 'url' => $url, 'label' => mb_substr($label ?: 'Obrázok / odkaz', 0, 255),
                    ]);
                } else {
                    $token = $link->token;
                }
                $href = URL::secure(URL::route('emailing.click', ['token' => $token], false));
                return '<a'.$m[1].'href='.$m[2].e($href).$m[2].$m[4].'>'.$m[5].'</a>';
            }, $html);
        }
        $footer = '';
        if ($campaign->track_clicks || $campaign->track_opens) {
            $footer = '<p style="text-align:center;font:11px Arial,sans-serif;color:#64748b">Táto ponuka meria '.($campaign->track_clicks ? 'návštevy odkazov' : '').($campaign->track_clicks && $campaign->track_opens ? ' a ' : '').($campaign->track_opens ? 'načítanie obrázka na odhad otvorenia' : '').'. Bez analytických cookies.</p>';
        }
        if ($campaign->track_opens) {
            $token = $delivery->open_token ?: Str::random(64);
            DB::table('mailing_deliveries')->where('id', $delivery->id)->update(['open_token' => $token]);
            $url = URL::secure(URL::route('emailing.open', ['token' => $token], false));
            $footer .= '<img src="'.e($url).'" width="1" height="1" alt="" style="border:0" />';
        }
        return str_replace('</body>', $footer.'</body>', $html);
    }
}
