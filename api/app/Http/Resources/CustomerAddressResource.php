<?php

namespace App\Http\Resources;

use App\Support\AddressFormatter;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerAddressResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'         => $this->id,
            'label'      => $this->label,
            'company'    => $this->company,
            'name'       => $this->name,
            'street'     => $this->street,
            'postcode'   => AddressFormatter::formatPostcode($this->postcode),
            'city'       => $this->city,
            'country'    => $this->country,
            'phone'      => $this->phone,
            'note'       => $this->note,
            'is_default' => (bool) $this->is_default,
            'summary'    => $this->summary,
            'created_at' => $this->created_at?->format('d.m.Y'),
        ];
    }
}
