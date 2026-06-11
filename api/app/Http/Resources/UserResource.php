<?php

namespace App\Http\Resources;

use App\Filters\OrderFilter;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderStatisticsService;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

class UserResource extends JsonResource
{
    public function toArray($request)
    {
        if (! $this->resource) {
            return [
                'isAuth' => false,
                'navigation' => [
                    'main' => self::publicNavigation(),
                    'userMenu' => self::guestUserMenu(),
                ],
            ];
        }

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'isAuth' => true,
            'firstName' => $this->firstName,
            'lastName' => $this->lastName,
            'username' => $this->username,
            'email' => $this->email,
            'phone' => $this->phone,
            'customer_id' => $this->customer_id,
            'status' => $this->statusData(),
            'order' => app(OrderStatisticsService::class)
                ->navigationSummary($this->resource, app(OrderFilter::class))['orders'],
            'roles' => $this->getRoleNames(),
            'can' => $this->buildCan(),
            'navigation' => [
                'main' => $this->mainNavigation(),
                'userMenu' => $this->userMenuNavigation(),
            ],
        ];
    }

    private function buildCan(): array
    {
        $user = $this->resource;

        return [
            'orders.create'        => Gate::forUser($user)->check('create', Order::class),
            'customers.create'     => $user->can('customers.create'),
            'products.create'      => $user->can('products.create'),
            'stocks.create'        => $user->can('stocks.create'),
            'users.create'         => $user->can('users.update'),
            'categories.manage'    => $user->can('categories.manage'),
            'announcements.manage' => $user->can('announcements.manage'),
        ];
    }

    private function mainNavigation(): array
    {
        $user = $this->resource;
        $isPortalUser = $user->customer_id !== null;

        $items = [
            ['NAME' => 'Dashboard', 'ROUTE' => 'dashboard.index', 'URL' => '/dashboard', 'ICON' => ''],
        ];

        if ($user->can('orders.viewAny') || $isPortalUser) {
            $items[] = ['NAME' => 'Objednávky', 'ROUTE' => 'orders.index', 'URL' => route('orders.index'), 'ICON' => 'badge'];
        }

        if ($user->can('products.viewAny')) {
            $items[] = ['NAME' => 'Produkty', 'ROUTE' => 'products.index', 'URL' => route('products.index'), 'ICON' => ''];
        }

        if ($user->can('customers.viewAny')) {
            $items[] = ['NAME' => 'Zákazníci', 'ROUTE' => 'customers.index', 'URL' => route('customers.index'), 'ICON' => ''];
        }

        if ($user->can('users.viewAny')) {
            $items[] = ['NAME' => 'Použivatelia', 'ROUTE' => 'users.index', 'URL' => route('users.index'), 'ICON' => ''];
        }

        if ($user->can('stocks.viewAny')) {
            $items[] = ['NAME' => 'Sklad', 'ROUTE' => 'stocks.index', 'URL' => route('stocks.index'), 'ICON' => ''];
        }

        $items[] = ['NAME' => 'Kontakt', 'ROUTE' => 'public.contactUs', 'URL' => '/kontakt', 'ICON' => ''];

        return $items;
    }

    private function userMenuNavigation(): array
    {
        $user = $this->resource;

        $items = [
            ['NAME' => 'Dashboard', 'ROUTE' => 'dashboard.index', 'URL' => '/dashboard', 'ACTION' => null],
        ];

        $hasAdminAccess = $user->hasAnyPermission([
            'products.viewAny', 'customers.viewAny', 'stocks.viewAny',
            'users.viewAny', 'categories.manage', 'announcements.manage',
        ]);

        if ($hasAdminAccess) {
            $items[] = ['NAME' => 'Admin', 'ROUTE' => 'admin.index', 'URL' => '/admin', 'ACTION' => null];
        }

        if ($user->hasRole('super-admin')) {
            $items[] = ['NAME' => 'Doprava', 'ROUTE' => 'shipping-methods.index', 'URL' => '/admin/doprava', 'ACTION' => null];
            $items[] = ['NAME' => 'Platby',  'ROUTE' => 'payment-methods.index',  'URL' => '/admin/platby',  'ACTION' => null];
            $items[] = ['NAME' => 'Kupóny',  'ROUTE' => 'coupons.index',          'URL' => '/admin/kupony',  'ACTION' => null];
        }

        $items[] = ['NAME' => 'Odhlásiť', 'ROUTE' => null, 'URL' => null, 'ACTION' => 'logout'];

        return $items;
    }

    private static function publicNavigation(): array
    {
        return [
            ['NAME' => 'Kontakt', 'ROUTE' => 'public.contactUs', 'URL' => '/kontakt', 'ICON' => ''],
        ];
    }

    private static function guestUserMenu(): array
    {
        return [
            ['NAME' => 'Vstúpiť', 'ROUTE' => 'public.login.index', 'URL' => '/login', 'ACTION' => null],
        ];
    }
}
