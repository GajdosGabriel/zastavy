<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Posudok údajov zákazníka pre panel v administrácii.
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
            $issues[] = [
                'index' => $index,
                'field' => $issue['field'] ?? null,
                'label' => $this->label($issue['field'] ?? ''),
                'severity' => $issue['severity'] ?? 'notice',
                'source' => $issue['source'] ?? 'rule',
                'message' => $issue['message'] ?? '',
                'current' => $issue['current'] ?? null,
                'suggested' => $issue['suggested'] ?? null,
                'applicable' => ($issue['suggested'] ?? null) !== null && $issue['suggested'] !== '',
            ];
        }

        $applied = [];

        foreach ((array) ($this->applied ?? []) as $index => $change) {
            $applied[] = [
                'index' => $index,
                'field' => $change['field'] ?? null,
                'label' => $this->label($change['field'] ?? ''),
                'from' => $change['from'] ?? null,
                'to' => $change['to'] ?? null,
                'source' => $change['source'] ?? 'rule',
                'at' => $change['at'] ?? null,
            ];
        }

        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'score' => $this->score,
            'summary' => $this->summary,
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

    private function label(string $field): string
    {
        return [
            'name' => 'Kontaktná osoba',
            'company' => 'Názov firmy',
            'email' => 'E-mail',
            'phone' => 'Telefón',
            'street' => 'Ulica a číslo',
            'postcode' => 'PSČ',
            'city' => 'Mesto/obec',
            'ico' => 'IČO',
            'dic' => 'DIČ',
            'ic_dic' => 'IČ DPH',
        ][$field] ?? $field;
    }
}
