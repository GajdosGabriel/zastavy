<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    /**
     * Zhrnutie posudku pre odznak v zozname.
     *
     * Vracia null, kým kontrola nedobehla alebo kým nemá čo povedať — odznak
     * „všetko v poriadku" pri 1 900 riadkoch je len šum a zoznam by z neho
     * zošedivel. Odbavený posudok tiež mlčí.
     */
    private function reviewSummary(): ?array
    {
        $review = $this->relationLoaded('review') ? $this->review : null;

        if ($review === null || $review->reviewed_at === null || $review->resolved_at !== null) {
            return null;
        }

        $issues = (array) ($review->issues ?? []);

        if ($issues === []) {
            return null;
        }

        return [
            'score' => $review->score,
            'severity' => $review->topSeverity(),
            'count' => count($issues),
            'summary' => $review->summaryText(),
        ];
    }

    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $user = $request->user();
        $contact = $this->primaryUser ?? $this->latestUser;

        return [
            'id' => $this->id,
            'name' => $contact?->username,
            'company' => $this->company,
            'street' => $this->street,
            'city' => $this->city,
            'postcode' => $this->postcode,
            'ico' => $this->ico,
            'dic' => $this->dic,
            'ic_dic' => $this->ic_dic,
            'email' => $contact?->email ?? $this->email,
            'created_at' => $this->created_at,
            'phone' => $contact?->phone ?? $this->phone,
            'status' => $this->statusData(),
            'primary_user' => $contact ? new UserResource($contact) : null,
            'users' => UserResource::collection($this->whenLoaded('users')),
            'orders' => $this->ordersCount,
            'mark' =>  [
                'isActive' => isset($this->mark),
                'endpoint'    => route('customers.marks.store', $this->id),
            ],

            // Odznak kvality údajov v zozname. Zámerne len zhrnutie — celý
            // posudok si vypýta detail cez customers.review.show.
            'review' => $this->reviewSummary(),

            'endpoints' => [
                'index'     =>  route('customers.index'),
                'show'      =>  route('customers.show', $this->id),
                'update'    =>  route('customers.update', $this->id),
                'store'     =>  route('customers.store'),
                'destroy' => route('customers.destroy', $this->id),

            ],
            'permissions' => [
                'view' => [
                    'allowed' => $user?->can('view', $this->resource) ?? false,
                    'label' => __('actions.view'),
                ],
                'update' => [
                    'allowed' => $user?->can('update', $this->resource) ?? false,
                    'label' => __('actions.update'),
                ],
                'delete' => [
                    'allowed' => $user?->can('delete', $this->resource) ?? false,
                    'label' => __('actions.delete'),
                ],
                'archive' => [
                    'allowed' => $user?->can('archive', $this->resource) ?? false,
                    'label' => __('actions.archive'),
                ],
                'restore' => [
                    'allowed' => $user?->can('restore', $this->resource) ?? false,
                    'label' => __('actions.restore'),
                ],
            ],

            // 'navigations' => [
                // 'show' =>  $this->when(auth()->user()->can("view", $this->resource), [
                //     'name' => 'Zobraziť',
                //     'title' => 'Zobraziť položku',
                //     'action' => 'show',
                //     'url' => route('contacts.show', [$this->id]),
                //     'icon' => 'iconShow',
                // ]),

                // 'edit' =>  $this->when(auth()->user()->can("update", $this->resource), [
                //     'name' => 'Upraviť',
                //     'title' => 'Upraviť položku',
                //     'action' => 'edit',
                //     'url' => route('organizations.contacts.update', [$this->organization_id, $this->id]),
                //     'typeOfButton' => 'button',
                //     'icon' => 'iconEdit',
                // ]),

                // 'delete' => $this->when(auth()->user()->can("delete", $this->resource), [
                //     'name' => $this->deleted_at ? 'Obnoviť' : 'Zmazať',
                //     'title' =>  $this->deleted_at ? 'Obnoviť kontakt' : 'Zmazať položku',
                //     'action' => 'delete',
                //     'typeOfButton' => 'button',
                //     'url' => route('organizations.contacts.destroy', [$this->organization_id, $this->id]),
                //     'icon' =>  $this->deleted_at ? 'iconBack' : 'iconDelete',
                // ])
            // ],
        ];
    }
}
