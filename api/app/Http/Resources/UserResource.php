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
            'prefix' => $this->prefix,
            'firstName' => $this->firstName,
            'lastName' => $this->lastName,
            'postfix' => $this->postfix,
            'position' => $this->position,
            'fullName' => $this->resource->fullName(),
            'username' => $this->username,
            'email' => $this->email,
            'phone' => $this->phone,
            'locale' => $this->locale,
            'customer_id' => $this->customer_id,
            'status' => $this->statusData(),
            'active' => (bool) $this->active,
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
            $items[] = ['NAME' => 'Používatelia', 'ROUTE' => 'users.index', 'URL' => route('users.index'), 'ICON' => ''];
        }

        if ($user->can('stocks.viewAny')) {
            $items[] = ['NAME' => 'Sklad', 'ROUTE' => 'stocks.index', 'URL' => route('stocks.index'), 'ICON' => ''];
        }

        // Rovnaká podmienka ako SalesAccess::staff — inak by stránka vrátila 403.
        if ($user->isStaff() && $user->can('orders.create') && $user->can('orders.viewAny')) {
            $items[] = ['NAME' => 'Predaj', 'ROUTE' => 'sales.index', 'URL' => '/predaj', 'ICON' => ''];
        }

        $items[] = ['NAME' => 'Kontakt', 'ROUTE' => 'public.contactUs', 'URL' => '/kontakt', 'ICON' => ''];

        return $items;
    }

    private function userMenuNavigation(): array
    {
        $user = $this->resource;

        // Dashboard je v hlavnej navigácii, tu by bol duplicitný. Menu je rozdelené na sekcie
        // (ACTION 'header'), aby boli všetky admin stránky na jednom mieste.
        $items = [];

        if ($user->hasRole('super-admin')) {
            $items[] = ['NAME' => 'Obchod', 'ROUTE' => null, 'URL' => null, 'ACTION' => 'header'];
            $items[] = ['NAME' => 'Doprava', 'ROUTE' => 'shipping-methods.index', 'URL' => '/admin/doprava', 'ACTION' => null];
            $items[] = ['NAME' => 'Platby',  'ROUTE' => 'payment-methods.index',  'URL' => '/admin/platby',  'ACTION' => null];
            $items[] = ['NAME' => 'Kupóny',  'ROUTE' => 'coupons.index',          'URL' => '/admin/kupony',  'ACTION' => null];
            $items[] = ['NAME' => 'Vlastnosti produktov', 'ROUTE' => 'attributes.index', 'URL' => '/admin/vlastnosti', 'ACTION' => null];

            $items[] = ['NAME' => 'Komunikácia', 'ROUTE' => null, 'URL' => null, 'ACTION' => 'header'];
            $items[] = ['NAME' => 'Emailing', 'ROUTE' => 'emailing.index', 'URL' => '/admin/emailing', 'ACTION' => null];
            $items[] = ['NAME' => 'Oznamy',   'ROUTE' => 'announcements.index', 'URL' => '/oznamy', 'ACTION' => null];

            $items[] = ['NAME' => 'Systém', 'ROUTE' => null, 'URL' => null, 'ACTION' => 'header'];
            $items[] = ['NAME' => 'Denník udalostí',     'ROUTE' => 'system-logs.index',      'URL' => '/admin/dennik',              'ACTION' => null];
            $items[] = ['NAME' => 'Export zákazníkov',   'ROUTE' => 'customers.export.index', 'URL' => '/admin/export-zakaznikov',   'ACTION' => null];
            $items[] = ['NAME' => 'Export používateľov', 'ROUTE' => 'users.export.index',     'URL' => '/admin/export-pouzivatelov', 'ACTION' => null];
        }

        if ($items) {
            $items[] = ['NAME' => null, 'ROUTE' => null, 'URL' => null, 'ACTION' => 'divider'];
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
