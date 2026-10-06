<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EmailingMeasurement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmailingMeasurementController extends Controller
{
    private function measurable(Request $request, object $delivery): bool
    {
        // This is a conservative filter, not proof of a human visit. No IP,
        // user-agent, cookies or per-request browsing history are persisted.
        return $request->isMethod('get') && in_array($delivery->status, ['sending', 'sent'])
            && $delivery->attempted_at && \Carbon\Carbon::parse($delivery->attempted_at)->gte(now()->subDays(90))
            && ! preg_match('/bot|crawler|spider|scanner|safelinks|proofpoint|mimecast|barracuda|headless/i', $request->userAgent() ?? '')
            && ! str_contains(strtolower($request->header('Purpose', '').' '.$request->header('Sec-Purpose', '')), 'prefetch')
            && DB::table('mailing_contacts')->where('id', $delivery->contact_id)->whereNull('unsubscribed_at')->exists();
    }

    public function click(Request $request, string $token)
    {
        $link = DB::table('mailing_links')->where('token', $token)->first();
        abort_unless($link && EmailingMeasurement::safeDestination($link->url), 404);
        // Destination comes exclusively from stored campaign content, never
        // from a URL query parameter. Failed metrics must not break the link.
        try {
            $delivery = DB::table('mailing_deliveries')->find($link->delivery_id);
            if ($delivery && $this->measurable($request, $delivery)) {
                DB::transaction(function () use ($link, $delivery) {
                    DB::table('mailing_links')->where('id', $link->id)->whereNull('clicked_at')->update(['clicked_at' => now()]);
                    DB::table('mailing_links')->where('id', $link->id)->increment('click_count');
                    DB::table('mailing_deliveries')->where('id', $delivery->id)->whereNull('clicked_at')->update(['clicked_at' => now()]);
                });
            }
        } catch (\Throwable $e) {
            report($e);
        }
        return redirect()->away($link->url, 302)->withHeaders([
            'Cache-Control' => 'no-store, private', 'Referrer-Policy' => 'no-referrer', 'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    public function open(Request $request, string $token)
    {
        try {
            $delivery = DB::table('mailing_deliveries')->where('open_token', $token)->first();
            if ($delivery && $this->measurable($request, $delivery)) {
                DB::transaction(function () use ($delivery) {
                    DB::table('mailing_deliveries')->where('id', $delivery->id)->whereNull('opened_at')->update(['opened_at' => now()]);
                    DB::table('mailing_deliveries')->where('id', $delivery->id)->increment('open_count');
                });
            }
        } catch (\Throwable $e) {
            report($e);
        }
        // Identical image even for an unknown token; never expose recipient data.
        return response(base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'), 200, [
            'Content-Type' => 'image/gif', 'Cache-Control' => 'no-store, private',
            'Referrer-Policy' => 'no-referrer', 'X-Content-Type-Options' => 'nosniff', 'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }
}
