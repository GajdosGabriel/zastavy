<?php

namespace App\Http\Resources;

use App\Filters\OrderFilter;
use App\Services\OrderStatisticsService;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array|Arrayable|\JsonSerializable
     */
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
            'order' => new OrderStatisticResource(
                app(OrderStatisticsService::class)->handle($this->resource, app(OrderFilter::class))
            ),
            'roles' => $this->getRoleNames(),
            'navigation' => [
                'main' => $this->mainNavigation(),
                'userMenu' => $this->userMenuNavigation(),
            ],
        ];
    }

    private function mainNavigation(): array
    {
        if ($this->hasAnyRole(['super-admin', 'admin'])) {
            return self::superAdminNavigation();
        }

        return self::userNavigation();
    }

    private function userMenuNavigation(): array
    {
        $items = [
            [
                'NAME' => 'Dashboard',
                'ROUTE' => 'dashboard.index',
                'URL' => '/dashboard',
                'ACTION' => null,
            ],
        ];

        if ($this->hasAnyRole(['super-admin', 'admin'])) {
            $items[] = [
                'NAME' => 'Admin',
                'ROUTE' => 'admin.index',
                'URL' => '/admin',
                'ACTION' => null,
            ];
        }

        $items[] = [
            'NAME' => 'Odhlásiť',
            'ROUTE' => null,
            'URL' => null,
            'ACTION' => 'logout',
        ];

        return $items;
    }

    private static function userNavigation(): array
    {
        return [
            [
                'NAME' => 'Dashboard',
                'ROUTE' => 'dashboard.index',
                'URL' => '/dashboard',
                'ICON' => '',
            ],
            [
                'NAME' => 'Objednávky',
                'ROUTE' => 'orders.index',
                'URL' => route('orders.index'),
                'ICON' => 'badge',
            ],
        ];
    }

    private static function superAdminNavigation(): array
    {
        return [
            [
                'NAME' => 'Dashboard',
                'ROUTE' => 'dashboard.index',
                'URL' => '/dashboard',
                'ICON' => '',
            ],
            [
                'NAME' => 'Objednávky',
                'ROUTE' => 'orders.index',
                'URL' => route('orders.index'),
                'ICON' => 'badge',
            ],
            [
                'NAME' => 'Produkty',
                'ROUTE' => 'products.index',
                'URL' => route('products.index'),
                'ICON' => '',
            ],
            [
                'NAME' => 'Zákazníci',
                'ROUTE' => 'customers.index',
                'URL' => route('customers.index'),
                'ICON' => '',
            ],
            [
                'NAME' => 'Pouzivatelia',
                'ROUTE' => 'users.index',
                'URL' => route('users.index'),
                'ICON' => '',
            ],
            [
                'NAME' => 'Sklad',
                'ROUTE' => 'stocks.index',
                'URL' => route('stocks.index'),
                'ICON' => '',
            ],
            [
                'NAME' => 'Kontakt',
                'ROUTE' => 'public.contactUs',
                'URL' => '/kontakt',
                'ICON' => '',
            ],
        ];
    }

    private static function publicNavigation(): array
    {
        return [
            [
                'NAME' => 'Kontakt',
                'ROUTE' => 'public.contactUs',
                'URL' => '/kontakt',
                'ICON' => '',
            ],
        ];
    }

    private static function guestUserMenu(): array
    {
        return [
            [
                'NAME' => 'Vstúpiť',
                'ROUTE' => 'public.login.index',
                'URL' => '/login',
                'ACTION' => null,
            ],
        ];
    }
}
