<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\SystemLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Denník udalostí — čo komu odišlo, čo zlyhalo, prihlásenia. Len čítanie.
 * Zapisuje App\Listeners\SystemLogSubscriber a App\Services\SystemLog\Recorder,
 * staré záznamy maže model:prune.
 */
class SystemLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasRole('super-admin'), 403, 'Denník je dostupný iba pre super-admina.');

        $validated = $request->validate([
            'channel' => ['nullable', 'string', 'max:32'],
            'level' => ['nullable', Rule::in(SystemLog::LEVELS)],
            'status' => ['nullable', Rule::in(SystemLog::STATUSES)],
            'search' => ['nullable', 'string', 'max:191'],
            'recipient' => ['nullable', 'string', 'max:191'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        // Telá mailov sa v zozname neťahajú, stačí vedieť, či nejaké je.
        $logs = SystemLog::query()
            ->select(['id', 'created_at', 'level', 'channel', 'event', 'status', 'message', 'recipient', 'user_id', 'ip', 'context'])
            ->selectRaw('body IS NOT NULL as has_body')
            ->with('user:id,email')
            ->tap(fn (Builder $query) => $this->filter($query, $validated))
            ->orderByDesc('id')
            ->paginate($validated['per_page'] ?? 50);

        return response()->json([
            'data' => $logs->getCollection()->map(fn (SystemLog $log) => $this->row($log, (bool) $log->has_body))->values(),
            'meta' => [
                'currentPage' => $logs->currentPage(),
                'lastPage' => $logs->lastPage(),
                'total' => $logs->total(),
            ],
            'summary' => $this->summary(),
            'channels' => DB::table('system_logs')->distinct()->orderBy('channel')->pluck('channel'),
            'retention' => ['days' => (int) config('logging.system_log.days')],
        ]);
    }

    /** Detail záznamu aj s telom odoslaného e-mailu. */
    public function show(Request $request, SystemLog $systemLog): JsonResponse
    {
        abort_unless($request->user()?->hasRole('super-admin'), 403, 'Denník je dostupný iba pre super-admina.');

        $systemLog->load('user:id,email');

        return response()->json([
            'data' => $this->row($systemLog, $systemLog->body !== null) + ['body' => $systemLog->body],
        ]);
    }

    /** @return array<string, mixed> */
    private function row(SystemLog $log, bool $hasBody): array
    {
        return [
            'id' => $log->id,
            'createdAt' => $log->created_at?->toIso8601String(),
            'level' => $log->level,
            'channel' => $log->channel,
            'event' => $log->event,
            'status' => $log->status,
            'message' => $log->message,
            'recipient' => $log->recipient,
            'user' => $log->user_id ? ['id' => $log->user_id, 'email' => $log->user?->email] : null,
            'ip' => $log->ip,
            'context' => $log->context,
            'hasBody' => $hasBody,
        ];
    }

    /** @param array<string, mixed> $filters */
    private function filter(Builder $query, array $filters): void
    {
        $query
            ->when($filters['channel'] ?? null, fn ($q, $value) => $q->where('channel', $value))
            ->when($filters['level'] ?? null, fn ($q, $value) => $q->where('level', $value))
            ->when($filters['status'] ?? null, fn ($q, $value) => $q->where('status', $value))
            ->when($filters['recipient'] ?? null, fn ($q, $value) => $q->where('recipient', $value))
            ->when($filters['date_from'] ?? null, fn ($q, $value) => $q->where('created_at', '>=', $value.' 00:00:00'))
            ->when($filters['date_to'] ?? null, fn ($q, $value) => $q->where('created_at', '<=', $value.' 23:59:59'))
            ->when($filters['search'] ?? null, function ($q, $value) {
                $pattern = '%'.addcslashes($value, '%_\\').'%';
                $q->where(fn ($q) => $q->where('recipient', 'like', $pattern)->orWhere('message', 'like', $pattern));
            });
    }

    /** @return array<string, int> */
    private function summary(): array
    {
        $day = now()->subDay();

        $row = DB::table('system_logs')
            ->where('created_at', '>=', now()->subDays(7))
            ->selectRaw("COALESCE(SUM(event = 'mail.sent' AND created_at >= ?), 0) as sent_day", [$day])
            ->selectRaw("COALESCE(SUM(event = 'mail.sent'), 0) as sent_week")
            ->selectRaw("COALESCE(SUM(event = 'mail.failed' AND created_at >= ?), 0) as failed_day", [$day])
            ->selectRaw("COALESCE(SUM(event = 'mail.failed'), 0) as failed_week")
            ->selectRaw("COALESCE(SUM(event = 'auth.login' AND created_at >= ?), 0) as logins_day", [$day])
            ->selectRaw("COALESCE(SUM(event = 'auth.failed' AND created_at >= ?), 0) as auth_failed_day", [$day])
            ->first();

        return [
            'sentDay' => (int) $row->sent_day,
            'sentWeek' => (int) $row->sent_week,
            'failedDay' => (int) $row->failed_day,
            'failedWeek' => (int) $row->failed_week,
            'loginsDay' => (int) $row->logins_day,
            'authFailedDay' => (int) $row->auth_failed_day,
        ];
    }
}
