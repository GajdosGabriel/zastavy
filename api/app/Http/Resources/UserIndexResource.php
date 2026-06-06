<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserIndexResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'firstName' => $this->firstName,
            'lastName' => $this->lastName,
            'username' => $this->username,
            'email' => $this->email,
            'phone' => $this->phone,
            'customer_id' => $this->customer_id,
            'customer' => $this->whenLoaded('customer', fn () => [
                'id' => $this->customer?->id,
                'company' => $this->customer?->company,
                'city' => $this->customer?->city,
            ]),
            'roles' => $this->getRoleNames(),
            'orders_count' => $this->orders_count ?? 0,
            'created_at' => $this->created_at?->format('d.m.Y H:i'),
            'endpoints' => [
                'index' => route('users.index'),
            ],
        ];
    }
}
