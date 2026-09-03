<?php

namespace App\Observers;

use App\Models\Customer;
use App\Services\Customers\CustomerReviewService;

/**
 * Zákazník sa uložil — naplánuj post-kontrolu jeho údajov.
 *
 * Hák visí na `saved()`, nie na controlleri. Dôvod je praktický: zákazník
 * vzniká a mení sa tromi cestami — z checkoutu (CustomerService::handleCheckout),
 * z administrácie (CustomerController) a z importu — a kontrola visiaca na
 * jednom controlleri by dve z nich prehliadla.
 *
 * Samotná kontrola sa tu nespúšťa, len sa naplánuje. Volanie registra a OpenAI
 * trvá sekundy a posledný krok objednávky naň nemá čakať.
 */
class CustomerObserver
{
    public function saved(Customer $customer): void
    {
        app(CustomerReviewService::class)->schedule($customer);
    }
}
