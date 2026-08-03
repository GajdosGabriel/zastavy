<?php

namespace App\Http\Resources\Concerns;

use App\Models\User;

trait StaffMeta
{
    /**
     * Resource-y produktov, kategórií a vlastností sa servírujú aj na verejných
     * endpointoch (katalóg, checkout). Mapa admin routes a blok permissions tam
     * nemá čo robiť — vracia sa iba personálu administrácie.
     *
     * Verejné routy nemajú `auth:sanctum`, takže default (web) guard nemusí
     * používateľa vôbec vyriešiť — preto sa pýtame najprv sanctum guardu.
     */
    protected function staffUser($request): ?User
    {
        $user = $request->user('sanctum') ?? $request->user();

        return $user?->hasAnyRole(['super-admin', 'admin', 'manager', 'sales', 'warehouse'])
            ? $user
            : null;
    }
}
