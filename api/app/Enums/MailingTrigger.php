<?php

namespace App\Enums;

/**
 * Udalosti, po ktorých sa dá spustiť automatická emailová kampaň.
 * Nový trigger = nový case + volanie EmailingService::trigger() na mieste, kde udalosť nastáva.
 */
enum MailingTrigger: string
{
    /** Vytvorená objednávka */
    case OrderCreated = 'order_created';

    /** Zásielka (dodací list) odovzdaná prepravcovi — udalosť sa viaže na konkrétnu zásielku */
    case ShippingDispatched = 'shipping_dispatched';

    /** Objednávka plne expedovaná (poslednú zásielku odovzdali prepravcovi) */
    case OrderCompleted = 'order_completed';

    public function label(): string
    {
        return match ($this) {
            self::OrderCreated       => 'Po vytvorení objednávky',
            self::ShippingDispatched => 'Po odovzdaní zásielky prepravcovi',
            self::OrderCompleted     => 'Po plnom vybavení objednávky',
        };
    }

    /** Krátky tvar do zoznamu kampaní: „Po objednávke · 24 h“ */
    public function shortLabel(): string
    {
        return match ($this) {
            self::OrderCreated       => 'Po objednávke',
            self::ShippingDispatched => 'Po expedícii',
            self::OrderCompleted     => 'Po vybavení objednávky',
        };
    }

    /** Najdlhšie povolené oneskorenie (365 dní). */
    public const MAX_DELAY_HOURS = 8760;

    /** Rýchle voľby oneskorenia v hodinách => popis; zadať sa dá aj vlastná hodnota. Každý trigger je vždy oneskorený. */
    public const DELAYS = [
        1 => '1 hodina',
        6 => '6 hodín',
        24 => '1 deň',
        48 => '2 dni',
        72 => '3 dni',
        168 => '7 dní',
        336 => '14 dní',
    ];

    public static function delayOptions(): array
    {
        return array_map(fn ($hours, $label) => ['value' => $hours, 'label' => $label], array_keys(self::DELAYS), self::DELAYS);
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        return array_map(fn (self $t) => ['value' => $t->value, 'label' => $t->label(), 'short' => $t->shortLabel()], self::cases());
    }
}
