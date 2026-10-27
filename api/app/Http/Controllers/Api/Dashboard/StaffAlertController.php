<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Notifications\StaffAlert;
use Illuminate\Http\Request;

/**
 * História upozornení v zvončeku. Každý vidí a označuje len svoje záznamy;
 * staré maže `notifications:prune` (config/alerts.php).
 */
class StaffAlertController extends Controller
{
    private const LIMIT = 50;

    public function index(Request $request)
    {
        $alerts = $request->user()->notifications()->where('type', StaffAlert::class);

        return response()->json([
            'data' => (clone $alerts)->limit(self::LIMIT)->get()->map(fn ($n) => [
                'id' => $n->id,
                'kind' => $n->data['kind'] ?? null,
                'title' => $n->data['title'] ?? '',
                'body' => $n->data['body'] ?? null,
                'url' => $n->data['url'] ?? null,
                'read_at' => $n->read_at,
                'created_at' => $n->created_at,
            ]),
            'unread' => (clone $alerts)->whereNull('read_at')->count(),
            'keep_days' => config('alerts.read_days'),
        ]);
    }

    public function read(Request $request, string $id)
    {
        $request->user()->notifications()->where('type', StaffAlert::class)->findOrFail($id)->markAsRead();

        return response()->noContent();
    }

    public function readAll(Request $request)
    {
        $request->user()->unreadNotifications()->where('type', StaffAlert::class)->update(['read_at' => now()]);

        return response()->noContent();
    }
}
