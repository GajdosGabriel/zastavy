<?php

namespace App\Enums;

use App\Models\Product;
use App\Models\User;

enum ModelStatus: string
{
    case Draft        = 'draft';
    case Active       = 'active';
    case Hidden       = 'hidden';
    case OutOfStock   = 'out_of_stock';
    case Discontinued = 'discontinued';
    case Cancelled    = 'cancelled';
    case Archived     = 'archived';
    case Blocked      = 'blocked';

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

    public function label(): string
    {
        return __('statuses.' . $this->value);
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Active       => 'green',
            self::OutOfStock   => 'amber',
            self::Hidden,
            self::Draft        => 'gray',
            self::Cancelled,
            self::Blocked      => 'red',
            self::Discontinued,
            self::Archived     => 'slate',
        };
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

    /**
     * @return array<int, array{value: string, label: string, color: string}>
     */
    public static function allowedForUser(?User $user): array
    {
        return array_map(fn (self $status) => $status->toArray(), self::allowedCasesForUser($user));
    }

    /**
     * @return array<int, string>
     */
    public static function allowedValuesForUser(?User $user): array
    {
        return array_map(fn (self $status) => $status->value, self::allowedCasesForUser($user));
    }

    /**
     * Statuses relevant for a user account (not product-specific).
     *
     * @return array<int, array{value: string, label: string, color: string}>
     */
    public static function allowedForUserAccount(?User $authUser): array
    {
        $cases = [self::Draft, self::Active, self::Archived];

        if (self::isSuperAdmin($authUser)) {
            $cases[] = self::Blocked;
        }

        return array_map(fn (self $status) => $status->toArray(), $cases);
    }

    public static function fromProduct(Product $product): self
    {
        if ($product->status instanceof self && $product->status !== self::Active) {
            return $product->status;
        }

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

    /**
     * @return array<int, self>
     */
    protected static function allowedCasesForUser(?User $user): array
    {
        if (self::isSuperAdmin($user)) {
            return self::cases();
        }

        if (self::isAdmin($user)) {
            return array_values(array_filter(
                self::cases(),
                fn (self $status) => !in_array($status, [self::Cancelled, self::Blocked], true)
            ));
        }

        return [
            self::Draft,
            self::Active,
            self::Hidden,
            self::OutOfStock,
            self::Discontinued,
            self::Archived,
        ];
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
            return $user->roles->pluck('name')->intersect(['super-admin', 'admin'])->isNotEmpty();
        }

        return false;
    }

    protected static function isSuperAdmin(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        if (method_exists($user, 'hasRole')) {
            return $user->hasRole('super-admin');
        }

        if (isset($user->role)) {
            return $user->role === 'super-admin';
        }

        if ($user->relationLoaded('roles')) {
            return $user->roles->pluck('name')->contains('super-admin');
        }

        return false;
    }
}
