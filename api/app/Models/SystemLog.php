<?php

namespace App\Models;

use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Záznam v denníku udalostí. Zapisuje sa cez App\Services\SystemLog\Recorder,
 * nie priamo — ten sa postará, aby zlyhanie zápisu nezhodilo request.
 */
class SystemLog extends Model
{
    use MassPrunable;

    public const UPDATED_AT = null;

    public const LEVELS = ['info', 'warning', 'error'];

    public const STATUSES = ['sent', 'failed', 'ok'];

    protected $fillable = [
        'level', 'channel', 'event', 'status', 'message', 'recipient',
        'user_id', 'context', 'ip',
    ];

    protected $casts = [
        'context' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /** Denník sa drží 30 dní (config/logging.php). */
    public function prunable()
    {
        return static::query()->where('created_at', '<', now()->subDays(max(1, (int) config('logging.system_log.days', 30))));
    }
}
