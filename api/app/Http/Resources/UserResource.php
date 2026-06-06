<?php

namespace App\Http\Resources;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\OrderStatisticResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        if (! $this->resource) {
            return [
                'isAuth' => false,
                'navigation' => [
                    'main' => self::publicNavigation(),
                ],
            ];
        }

        return [
            'id' => $this->id,
            'isAuth' => true,
            'firstName' => $this->firstName,
            'lastName' => $this->lastName,
            'username' => $this->username,
            'email' => $this->email,
            'phone' => $this->phone,
            'customer_id' => $this->customer_id,
            'order' => new OrderStatisticResource($request),
            'roles' => $this->getRoleNames(),
            'navigation' => [
                'main' => $this->hasBackofficeRole()
                    ? self::privateNavigation()
                    : self::publicNavigation(),
            ],
        ];
    }

    private function hasBackofficeRole(): bool
    {
        return $this->hasAnyRole([
            'super-admin',
            'admin',
            'manager',
            'sales',
            'warehouse',
        ]);
    }

    private static function privateNavigation(): array
    {
        return [
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
}
