<?php

namespace App\Enums;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;

enum ModelStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Hidden = 'hidden';
    case OutOfStock = 'out_of_stock';
    case Discontinued = 'discontinued';
    case Processing = 'processing';
    case PartiallyShipped = 'partially_shipped';
    case Shipped = 'shipped';
    case Cancelled = 'cancelled';
    case Archived = 'archived';
    case Blocked = 'blocked';

    public function isPubliclyVisible(): bool
    {
        return in_array($this, [self::Active, self::OutOfStock], true);
    }

    public function isSellable(): bool
    {
        return $this === self::Active;
    }

    public function isArchived(): bool
    {
        return in_array($this, [self::Archived, self::Discontinued], true);
    }

    public function isOrderStatus(): bool
    {
        return in_array($this, [
            self::Processing,
            self::PartiallyShipped,
            self::Shipped,
            self::Cancelled,
        ], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Koncept',
            self::Active => 'Aktívny',
            self::Hidden => 'Skrytý',
            self::OutOfStock => 'Vypredaný',
            self::Discontinued => 'Vyradený',
            self::Processing => 'Spracováva sa',
            self::PartiallyShipped => 'Čiastočne expedovaný',
            self::Shipped => 'Expedovaný',
            self::Cancelled => 'Stornovaný',
            self::Archived => 'Archivovaný',
            self::Blocked => 'Blokovaný',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Active => 'green',
            self::Processing, self::PartiallyShipped => 'blue',
            self::OutOfStock => 'amber',
            self::Hidden, self::Draft => 'gray',
            self::Cancelled, self::Blocked => 'red',
            self::Shipped => 'emerald',
            self::Discontinued, self::Archived => 'slate',
        };
    }

    /**
     * Returns the allowed statuses for a given user as [value, label] pairs.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function allowedForUser(?User $user): array
    {
        $cases = self::isAdmin($user)
            ? self::cases()
            : [
                self::Draft,
                self::Active,
                self::Hidden,
                self::OutOfStock,
                self::Discontinued,
                self::Archived,
            ];

        return array_map(fn (self $status) => $status->toArray(), $cases);
    }

    /**
     * @return array{value: string, label: string, color: string}
     */
    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'label' => $this->label(),
            'color' => $this->badgeColor(),
        ];
    }

    public static function fromProduct(Product $product): self
    {
        if ($product->deleted_at !== null) {
            return self::Archived;
        }

        if (!$product->published) {
            return self::Hidden;
        }

        if ($product->quantity !== null && (int) $product->quantity <= 0) {
            return self::OutOfStock;
        }

        return self::Active;
    }

    public static function fromOrder(Order $order): self
    {
        if ($order->isStorned()) {
            return self::Cancelled;
        }

        if ($order->isFinished()) {
            return self::Shipped;
        }

        if ($order->stockExpedition > 0) {
            return self::PartiallyShipped;
        }

        return self::Processing;
    }

    protected static function isAdmin(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        if (method_exists($user, 'hasRole')) {
            return $user->hasRole('super-admin') || $user->hasRole('admin');
        }

        if (isset($user->role)) {
            return in_array($user->role, ['super-admin', 'admin'], true);
        }

        if ($user->relationLoaded('roles')) {
            return $user->roles
                ->pluck('name')
                ->intersect(['super-admin', 'admin'])
                ->isNotEmpty();
        }

        return false;
    }
}
