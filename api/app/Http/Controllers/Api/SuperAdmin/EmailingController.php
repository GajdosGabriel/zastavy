<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\User;
use App\Services\EmailingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class EmailingController extends Controller
{
    private function content(Request $r): array
    {
        $data = $r->validate([
            'layout' => ['sometimes', 'required', Rule::in(['default', 'spisor', 'flags-catalog'])],
            'name' => 'required|string|max:255', 'subject' => 'required|string|max:200|not_regex:/[\r\n]/',
            'heading' => 'required|string|max:200', 'body' => 'required|string|max:20000',
            'button_label' => 'nullable|string|max:80', 'button_url' => 'nullable|url:http,https|max:2048',
            'coupon_id' => ['nullable', Rule::exists('coupons', 'id')->whereNull('deleted_at')],
        ]);

        return $data + ['layout' => 'default'];
    }

    public function index(Request $r, EmailingService $service)
    {
        $r->validate(['from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d|after_or_equal:from']);
        $from = $r->input('from', now()->subDays(29)->toDateString());
        $to = $r->input('to', now()->toDateString());
        $sent = DB::table('mailing_deliveries')->whereBetween('sent_at', [$from.' 00:00:00', $to.' 23:59:59']);

        return response()->json([
            'campaigns' => DB::table('mailing_campaigns')->orderByDesc('id')->paginate(20),
            'templates' => DB::table('mailing_templates')->orderBy('name')->get(),
            'coupons' => Coupon::where('active', true)->orderByDesc('id')->get(['id', 'code', 'type', 'value', 'valid_to']),
            'stats' => [
                'sent' => (clone $sent)->count(),
                // Cohort: engagement of messages sent in the selected interval.
                'opened' => (clone $sent)->whereNotNull('opened_at')->count(),
                'clicked' => (clone $sent)->whereNotNull('clicked_at')->count(),
                'daily' => (clone $sent)->selectRaw('DATE(sent_at) as day, COUNT(*) as total')->groupByRaw('DATE(sent_at)')->orderBy('day')->get(),
                'active' => DB::table('mailing_contacts')->whereNull('unsubscribed_at')->count(),
                'unsubscribed' => DB::table('mailing_contacts')->whereNotNull('unsubscribed_at')->count(),
                'pending' => DB::table('mailing_deliveries')->where('status', 'pending')->count(),
                'failed' => DB::table('mailing_deliveries')->where('status', 'failed')->whereBetween('attempted_at', [$from.' 00:00:00', $to.' 23:59:59'])->count(),
            ],
            'ready' => $service->ready(), 'hourly_limit' => config('emailing.hourly_limit'),
            'timezone' => config('app.timezone'),
        ]);
    }

    public function save(Request $r, ?int $id = null)
    {
        $data = $this->content($r) + $r->validate([
            'trigger_event' => ['nullable', Rule::in(['order_created'])],
            'delay_hours' => 'required_with:trigger_event|integer|min:1|max:8760',
            'track_clicks' => 'sometimes|boolean', 'track_opens' => 'sometimes|boolean',
        ]) + ['trigger_event' => null, 'delay_hours' => 24, 'track_clicks' => false, 'track_opens' => false];
        $id = DB::transaction(function () use ($r, $id, $data) {
            if ($id) {
                $campaign = DB::table('mailing_campaigns')->where('id', $id)->lockForUpdate()->first();
                abort_unless($campaign, 404);
                abort_unless($campaign->status === 'draft', 422, 'Upravovať sa dá iba koncept.');
                DB::table('mailing_campaigns')->where('id', $id)->update($data + ['updated_at' => now()]);

                return $id;
            }

            return DB::table('mailing_campaigns')->insertGetId($data + ['created_by' => $r->user()->id, 'created_at' => now(), 'updated_at' => now()]);
        });

        return DB::table('mailing_campaigns')->find($id);
    }

    public function template(Request $r)
    {
        $data = $this->content($r);
        unset($data['coupon_id']);
        $id = DB::table('mailing_templates')->insertGetId($data + ['created_at' => now(), 'updated_at' => now()]);

        return DB::table('mailing_templates')->find($id);
    }

    public function preview(Request $r, EmailingService $service)
    {
        $data = $this->content($r);
        $data['coupon_code'] = isset($data['coupon_id']) ? Coupon::find($data['coupon_id'])?->code : null;

        return response()->json(['html' => $service->html((object) ($data + ['button_url' => null, 'button_label' => null]))]);
    }

    public function test(Request $r, EmailingService $service)
    {
        abort_unless($service->ready(), 422, 'Najprv zapnite emailing a nastavte SMTP.');
        $data = $this->content($r);
        $data['coupon_code'] = isset($data['coupon_id']) ? Coupon::find($data['coupon_id'])?->code : null;
        // Test messages go only to the authenticated administrator.
        $service->send((object) ($data + ['button_url' => null, 'button_label' => null]), (object) ['email' => $r->user()->email], true);

        return response()->json(['message' => 'Test bol odoslaný na váš prihlasovací email.']);
    }

    public function queue(Request $r, int $id, EmailingService $service)
    {
        abort_unless($service->ready(), 422, 'Najprv zapnite emailing a nastavte SMTP.');
        $r->validate(['scheduled_at' => 'nullable|date|after_or_equal:now']);

        return DB::transaction(function () use ($r, $id) {
            $campaign = DB::table('mailing_campaigns')->where('id', $id)->lockForUpdate()->first();
            abort_unless($campaign, 404);
            abort_unless($campaign->status === 'draft', 422, 'Táto kampaň už bola zaradená.');
            $coupon = $campaign->coupon_id ? Coupon::find($campaign->coupon_id) : null;
            abort_if($campaign->coupon_id && (! $coupon || ! $coupon->active || ($coupon->valid_to && $coupon->valid_to->endOfDay()->isPast())), 422, 'Kupón nie je aktívny alebo už vypršal.');
            $total = 0;
            if (! $campaign->trigger_event) DB::table('mailing_contacts')->whereNull('unsubscribed_at')->orderBy('id')->chunkById(500, function ($contacts) use ($id, &$total) {
                $rows = $contacts->map(fn ($c) => ['campaign_id' => $id, 'contact_id' => $c->id, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()])->all();
                DB::table('mailing_deliveries')->insert($rows);
                $total += count($rows);
            });
            abort_if($campaign->trigger_event && $r->filled('scheduled_at'), 422, 'Automatizácia sa aktivuje ihneď; oneskorenie sa počíta od objednávky.');
            abort_unless($campaign->trigger_event || $total > 0, 422, 'Najprv pridajte oprávnených príjemcov.');
            DB::table('mailing_campaigns')->where('id', $id)->update(['status' => 'queued', 'coupon_code' => $coupon?->code, 'scheduled_at' => $r->input('scheduled_at') ? Carbon::parse($r->input('scheduled_at'))->setTimezone(config('app.timezone')) : now(), 'updated_at' => now()]);

            return response()->json(['recipients' => $total]);
        });
    }

    public function cancel(int $id)
    {
        DB::transaction(function () use ($id) {
            $campaign = DB::table('mailing_campaigns')->where('id', $id)->lockForUpdate()->first();
            abort_unless($campaign, 404);
            abort_unless($campaign->status === 'queued', 422, 'Kampaň už nie je vo fronte.');
            DB::table('mailing_campaigns')->where('id', $id)->update(['status' => 'cancelled', 'updated_at' => now()]);
            DB::table('mailing_deliveries')->where('campaign_id', $id)->where('status', 'pending')->update(['status' => 'skipped', 'updated_at' => now()]);
        });

        return response()->noContent();
    }

    public function deliveries(Request $r, int $id)
    {
        abort_unless(DB::table('mailing_campaigns')->where('id', $id)->exists(), 404);

        return response()->json([
            'engagement' => [
                'opened' => DB::table('mailing_deliveries')->where('campaign_id', $id)->whereNotNull('opened_at')->count(),
                'clicked' => DB::table('mailing_deliveries')->where('campaign_id', $id)->whereNotNull('clicked_at')->count(),
            ],
            'links' => DB::table('mailing_links as l')->join('mailing_deliveries as d', 'd.id', '=', 'l.delivery_id')
                ->where('d.campaign_id', $id)->selectRaw('l.url, SUM(l.click_count) as clicks, COUNT(DISTINCT CASE WHEN l.clicked_at IS NOT NULL THEN d.id END) as messages')
                ->groupBy('l.url')->orderByDesc('clicks')->get(),
            'counts' => DB::table('mailing_deliveries')->where('campaign_id', $id)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
            'deliveries' => DB::table('mailing_deliveries as d')->join('mailing_contacts as c', 'c.id', '=', 'd.contact_id')->where('d.campaign_id', $id)->orderByDesc('d.id')->select('d.*', 'c.email', 'c.name')->paginate(50),
        ]);
    }

    public function contacts(Request $r)
    {
        $r->validate(['search' => 'nullable|string|max:255']);

        return DB::table('mailing_contacts')->when($r->input('search'), fn ($q, $s) => $q->where('email', 'like', '%'.$s.'%'))->orderByDesc('id')->paginate(50);
    }

    public function addContacts(Request $r)
    {
        $data = $r->validate(['emails' => 'nullable|string|max:50000', 'import_customers' => 'boolean', 'permission_note' => 'required|string|min:5|max:255', 'confirmed' => 'accepted']);
        $addresses = collect(preg_split('/[\s,;]+/', trim($data['emails'] ?? ''), -1, PREG_SPLIT_NO_EMPTY))->map(fn ($email) => ['email' => $email, 'name' => null]);
        if ($r->boolean('import_customers')) {
            $addresses = $addresses->concat(User::whereNotNull('customer_id')->where('active', true)->whereHas('customer')->get(['email', 'username'])->map(fn ($u) => ['email' => $u->email, 'name' => $u->username]));
        }
        $added = 0;
        $skipped = 0;
        foreach ($addresses as $address) {
            $email = Str::lower(trim($address['email'] ?? ''));
            if (! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
                $skipped++;

                continue;
            }
            $inserted = DB::table('mailing_contacts')->insertOrIgnore(['email' => $email, 'name' => $address['name'], 'permission_note' => $data['permission_note'], 'permission_at' => now(), 'created_by' => $r->user()->id, 'token' => Str::random(64), 'created_at' => now(), 'updated_at' => now()]);
            $added += $inserted;
            $skipped += 1 - $inserted;
        }

        return response()->json(['added' => $added, 'skipped' => $skipped]);
    }

    public function suppress(int $id)
    {
        abort_unless(DB::table('mailing_contacts')->where('id', $id)->exists(), 404);
        DB::table('mailing_contacts')->where('id', $id)->whereNull('unsubscribed_at')->update(['unsubscribed_at' => now(), 'updated_at' => now()]);

        return response()->noContent();
    }

    public function unsubscribe(Request $r, string $token)
    {
        $contact = DB::table('mailing_contacts')->where('token', $token)->first();
        abort_unless($contact, 404);
        if ($r->isMethod('post')) {
            DB::table('mailing_contacts')->where('id', $contact->id)->whereNull('unsubscribed_at')->update(['unsubscribed_at' => now(), 'updated_at' => now()]);
        }

        return response()->view('emails.unsubscribe', ['done' => $r->isMethod('post') || $contact->unsubscribed_at])->header('Referrer-Policy', 'no-referrer')->header('Cache-Control', 'no-store');
    }
}
