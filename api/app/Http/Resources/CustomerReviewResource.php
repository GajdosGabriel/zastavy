<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Posudok údajov zákazníka pre panel v administrácii.
 *
 * Tu sa nález prekladá — až tu. V databáze leží ako kľúč a parametre, lebo
 * vznikol v nočnom behu, kde žiadny používateľ nesedel; jazyk sa vyberá podľa
 * toho, kto sa práve pýta (App\Http\Middleware\SetLocale).
 *
 * Výnimkou je nález od AI: model si vetu formuluje sám, kľúč k nej neexistuje
 * a preložiť sa nedá. Taký nález nesie `message` a berie sa, ako je.
 *
 * Výhrady si nesú svoj index v poli `issues` — presne ten sa vracia späť pri
 * prijatí návrhu. Prehliadač tak neposiela hodnoty, len „chcem toto", a zapíše
 * sa vždy to, čo kontrola naozaj navrhla.
 */
class CustomerReviewResource extends JsonResource
{
    public function toArray($request)
    {
        $issues = [];

        foreach ((array) ($this->issues ?? []) as $index => $issue) {
            $severity = $issue['severity'] ?? 'notice';
            $source = $issue['source'] ?? 'rule';

            $issues[] = [
                'index' => $index,
                'field' => $issue['field'] ?? null,
                'label' => $this->fieldLabel($issue['field'] ?? ''),
                'severity' => $severity,
                'severity_label' => __('customer_review.severities.'.$severity),
                'source' => $source,
                'source_label' => __('customer_review.sources.'.$source),
                'message' => $this->message($issue),
                'current' => $issue['current'] ?? null,
                'suggested' => $issue['suggested'] ?? null,
                'applicable' => ($issue['suggested'] ?? null) !== null && $issue['suggested'] !== '',
            ];
        }

        $applied = [];

        foreach ((array) ($this->applied ?? []) as $index => $change) {
            $source = $change['source'] ?? 'rule';

            $applied[] = [
                'index' => $index,
                'field' => $change['field'] ?? null,
                'label' => $this->fieldLabel($change['field'] ?? ''),
                'from' => $change['from'] ?? null,
                'to' => $change['to'] ?? null,
                'source' => $source,
                'source_label' => __('customer_review.sources.'.$source),
                'at' => $change['at'] ?? null,
            ];
        }

        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'score' => $this->score,
            'summary' => $this->resource->summaryText(),
            'severity' => $this->topSeverity(),
            'issues' => $issues,
            'applied' => $applied,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'pending' => $this->due_at !== null,
            'last_error' => $this->last_error,
            'endpoints' => [
                'show' => route('customers.review.show', $this->customer_id),
                'run' => route('customers.review.store', $this->customer_id),
                'apply' => route('customers.review.update', $this->customer_id),
                'revert' => route('customers.review.revert', $this->customer_id),
                'resolve' => route('customers.review.destroy', $this->customer_id),
            ],
        ];
    }

    /**
     * Veta nálezu.
     *
     * `message` je fallback pre nálezy od AI a pre posudky zapísané ešte pred
     * prechodom na kľúče — tie sa preložia až pri najbližšom prebehnutí.
     */
    private function message(array $issue): string
    {
        $key = $issue['key'] ?? null;

        if ($key === null) {
            return (string) ($issue['message'] ?? '');
        }

        return __($key, (array) ($issue['params'] ?? []));
    }

    private function fieldLabel(string $field): string
    {
        return $field === '' ? '' : __('customer_review.fields.'.$field);
    }
}
