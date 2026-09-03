<?php

namespace App\Models;

use App\Services\Customers\CustomerDataRules;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Posudok údajov jedného zákazníka — „ako je na tom tento riadok".
 *
 * Zapisuje ho výhradne App\Services\Customers\CustomerReviewService; model
 * sám drží len prevody a dopyty. Jeden riadok na zákazníka, prepisuje sa na
 * mieste — história posudkov nikoho nezaujíma, aktuálny stav áno.
 */
class CustomerReview extends Model
{
    protected $guarded = [];

    protected $casts = [
        'issues' => 'array',
        'applied' => 'array',
        'score' => 'integer',
        'due_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'notified_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /** Splatné kontroly, od najdlhšie čakajúcej. */
    public function scopeDue(Builder $query): Builder
    {
        return $query->whereNotNull('due_at')
            ->where('due_at', '<=', now())
            ->orderBy('due_at');
    }

    /** Posudky, ktoré ešte niekto nevidel a majú čo povedať. */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('resolved_at')
            ->whereNotNull('reviewed_at')
            ->whereRaw("JSON_LENGTH(COALESCE(issues, '[]')) > 0");
    }

    /**
     * Výhrady aspoň zadanej závažnosti.
     *
     * @return array<int, array<string, mixed>>
     */
    public function issuesAtLeast(string $severity): array
    {
        $order = array_flip(CustomerDataRules::SEVERITIES);
        $threshold = $order[$severity] ?? 0;

        return array_values(array_filter(
            (array) ($this->issues ?? []),
            static fn (array $issue) => ($order[$issue['severity'] ?? ''] ?? -1) >= $threshold,
        ));
    }

    /** Najvyššia závažnosť v posudku — z toho sa farbí odznak v zozname. */
    public function topSeverity(): ?string
    {
        $order = array_flip(CustomerDataRules::SEVERITIES);
        $top = null;

        foreach ((array) ($this->issues ?? []) as $issue) {
            $severity = $issue['severity'] ?? null;

            if ($severity !== null && ($order[$severity] ?? -1) > ($order[$top] ?? -1)) {
                $top = $severity;
            }
        }

        return $top;
    }

    /**
     * Výhrady, ktoré nesú konkrétny návrh — teda tie, ktoré vie admin
     * v detaile zákazníka prijať jedným klikom.
     *
     * @return array<int, array<string, mixed>>
     */
    public function actionableIssues(): array
    {
        $out = [];

        foreach ((array) ($this->issues ?? []) as $index => $issue) {
            if (($issue['suggested'] ?? null) !== null && $issue['suggested'] !== '') {
                $out[$index] = $issue;
            }
        }

        return $out;
    }
}
