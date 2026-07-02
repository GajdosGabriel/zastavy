<?php

namespace App\Enums;

use App\Models\Order;

enum OrderStatus: string
{
    /** Nová objednávka, čaká na spracovanie (uložené v DB) */
    case Draft = 'draft';

    /** Spracováva sa — žiadna expedícia ešte nezačala (vypočítané) */
    case Processing = 'processing';

    /** Pripravená na odoslanie, čaká na vyzdvihnutie kuriérom (uložené v DB) */
    case ReadyToShip = 'ready_to_ship';

    /** Čiastočne expedovaná (vypočítané) */
    case PartiallyShipped = 'partially_shipped';

    /** Plne expedovaná (vypočítané) */
    case Shipped = 'shipped';

    /** Stornovaná (uložené v DB) */
    case Cancelled = 'cancelled';

    /** Manuálne archivovaná adminom (uložené v DB) */
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft            => 'Nová',
            self::Processing       => 'Spracováva sa',
            self::ReadyToShip      => 'Pripravené na odoslanie',
            self::PartiallyShipped => 'Čiastočne expedovaná',
            self::Shipped          => 'Expedovaná',
            self::Cancelled        => 'Stornovaná',
            self::Archived         => 'Archivovaná',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Draft            => 'gray',
            self::Processing       => 'blue',
            self::ReadyToShip      => 'indigo',
            self::PartiallyShipped => 'amber',
            self::Shipped          => 'emerald',
            self::Cancelled        => 'red',
            self::Archived         => 'slate',
        };
    }

    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'label' => $this->label(),
            'color' => $this->badgeColor(),
        ];
    }

    public static function fromOrder(Order $order): self
    {
        if ($order->status === self::Cancelled || $order->isStorned()) {
            return self::Cancelled;
        }

        if ($order->status === self::Archived) {
            return self::Archived;
        }

        if ($order->isFinished()) {
            return self::Shipped;
        }

        if ($order->stockExpedition > 0) {
            return self::PartiallyShipped;
        }

        if ($order->status === self::ReadyToShip) {
            return self::ReadyToShip;
        }

        if (! $order->isOpened) {
            return self::Draft;
        }

        return self::Processing;
    }
}
