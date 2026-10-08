<?php

namespace App\Enums;

/**
 * Druh zákazníka — obec, škola, firma alebo súkromná osoba.
 *
 * Štyri hodnoty a žiadna „iné": farnosť, združenie či hasičský zbor sú
 * organizácie s IČO a faktúrou ako firma, tak patria medzi firmy.
 */
enum CustomerType: string
{
    case Municipality = 'municipality';
    case School       = 'school';
    case Company      = 'company';
    case Person       = 'person';

    public function label(): string
    {
        return __('customer_types.' . $this->value);
    }

    /**
     * @return array{value: string, label: string}
     */
    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'label' => $this->label(),
        ];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $type) => $type->toArray(), self::cases());
    }
}
