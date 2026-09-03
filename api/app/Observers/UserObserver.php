<?php

namespace App\Observers;

use App\Models\User;
use App\Services\Customers\CustomerReviewService;

/**
 * Zmenilo sa meno kontaktnej osoby — naplánuj zákazníkovi post-kontrolu.
 *
 * Meno kontaktu je jediné pole posudku, ktoré nesedí na `customers`; odkedy
 * `customers.name` neexistuje, `wasChanged()` na zákazníkovi o jeho zmene nevie
 * a CustomerObserver by ju prehliadol. Preklep v mene by tak zostal nepovšimnutý
 * až do najbližšej zmeny firemných údajov.
 *
 * E-mail ani telefón tu zámerne nie sú: posudok ich neposudzuje.
 */
class UserObserver
{
    public function saved(User $user): void
    {
        if (! $user->wasRecentlyCreated && ! $user->wasChanged(['username', 'firstName', 'lastName'])) {
            return;
        }

        // Načítaný zákazník, nie ten z pamäte volajúceho: schedule() rozlišuje
        // práve vytvorený riadok od zmeneného a inštancia čerstvá z databázy mu
        // dá správnu odpoveď aj vtedy, keď kontakt vzniká spolu s firmou.
        $customer = $user->customer()->first();

        if ($customer === null) {
            return;
        }

        app(CustomerReviewService::class)->schedule($customer, force: true);
    }
}
