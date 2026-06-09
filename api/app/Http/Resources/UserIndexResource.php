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
            'firstName' => $this->firstName,
            'lastName' => $this->lastName,
            'username' => $this->username,
            'email' => $this->email,
            'phone' => $this->phone,
            'customer_id' => $this->customer_id,
            'status' => $this->statusData(),
            'customer' => $this->whenLoaded('customer', fn () => [
                'id' => $this->customer?->id,
                'company' => $this->customer?->company,
                'city' => $this->customer?->city,
            ]),
            'roles' => $this->getRoleNames(),
            'permissions' => $this->getDirectPermissions()->pluck('name')->values(),
            'orders_count' => $this->orders_count ?? 0,
            'created_at' => $this->created_at?->format('d.m.Y H:i'),
            'endpoints' => [
                'index' => route('users.index'),
                'show' => route('users.show', $this->id),
                'update' => route('users.update', $this->id),
            ],
        ];
    }
}
