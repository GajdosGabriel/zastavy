<?php

namespace App\Http\Resources;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserIndexResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array|Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
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
            // Interná poznámka len pre správu účtov.
            'note' => $this->when((bool) $request->user()?->can('users.update'), $this->note),
            'customer_id' => $this->customer_id,
            'status' => $this->statusData(),
            'active' => (bool) $this->active,
            'customer' => $this->whenLoaded('customer', fn () => [
                'id' => $this->customer?->id,
                'company' => $this->customer?->company,
                'city' => $this->customer?->city,
            ]),
            'roles' => $this->getRoleNames(),
            'permissions' => $this->getDirectPermissions()->pluck('name')->values(),
            'orders_count' => $this->orders_count ?? 0,

            // Prihlásenia — stĺpce na users, takže lacné aj vo výpise.
            'last_login_at' => $this->last_login_at?->format('d.m.Y H:i'),
            'last_login_human' => $this->last_login_at?->diffForHumans(),
            'last_login_ip' => $this->last_login_ip,
            'login_count' => (int) ($this->login_count ?? 0),

            // Posledná aktivita a posledná objednávka — len keď je relácia načítaná (detail).
            'last_activity_at' => $this->whenLoaded('lastUsedToken', fn () => $this->lastUsedToken?->last_used_at?->format('d.m.Y H:i')),
            'last_activity_human' => $this->whenLoaded('lastUsedToken', fn () => $this->lastUsedToken?->last_used_at?->diffForHumans()),
            'last_order' => $this->whenLoaded('latestOrder', fn () => $this->latestOrder ? [
                'id' => $this->latestOrder->id,
                'uuid' => $this->latestOrder->uuid,
                'serial_number' => $this->latestOrder->serial_number,
                'created_at' => $this->latestOrder->created_at?->format('d.m.Y H:i'),
                'created_human' => $this->latestOrder->created_at?->diffForHumans(),
            ] : null),

            'email_verified_at' => $this->email_verified_at?->format('d.m.Y H:i'),
            'created_at' => $this->created_at?->format('d.m.Y H:i'),
            'created_human' => $this->created_at?->diffForHumans(),
            'updated_at' => $this->updated_at?->format('d.m.Y H:i'),
            'endpoints' => [
                'index' => route('users.index'),
                'show' => route('users.show', $this->id),
                'update' => route('users.update', $this->id),
            ],
        ];
    }
}
