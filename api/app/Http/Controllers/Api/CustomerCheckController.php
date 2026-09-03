<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Customers\CustomerDataRules;
use Illuminate\Http\Request;

/**
 * Kontrola údajov ešte pred uložením — to isté, čo po uložení robí prvá
 * vrstva post-kontroly, len okamžite a v formulári.
 *
 * Dôvod je jednoduchý: doteraz sme chyby len zbierali. IČO v poli „Názov
 * firmy", DIČ ako pomlčka a telefón s lomítkom sa do tabuľky dostali cez
 * formulár a post-kontrola ich vie nájsť až potom. Toto ich zastaví na
 * vstupe, kým je pri formulári ešte človek, ktorý tie údaje pozná.
 *
 * Nič nezapisuje a nič neblokuje — vracia rady. Tvrdé pravidlá, ktoré
 * uloženie zastavia, sedia vo `CustomerUpdateRequest` a týkajú sa len
 * administrácie; objednávku zákazníkovi neodmietneme pre preklep v IČO.
 *
 * Verejné, lebo verejný je aj checkout. Neposkytuje to nič, čo by volajúci
 * sám nenapísal do tela požiadavky — preto sa tu nedá nič vyčítať z databázy.
 */
class CustomerCheckController extends Controller
{
    public function __invoke(Request $request, CustomerDataRules $rules)
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:150',
            'company' => 'nullable|string|max:200',
            'email' => 'nullable|string|max:150',
            'phone' => 'nullable|string|max:40',
            'street' => 'nullable|string|max:250',
            'postcode' => 'nullable|string|max:20',
            'city' => 'nullable|string|max:100',
            'ico' => 'nullable|string|max:20',
            'dic' => 'nullable|string|max:200',
            'ic_dic' => 'nullable|string|max:200',
        ]);

        $issues = [];

        foreach ($rules->checkAttributes($validated) as $issue) {
            // Formulár si nálezy zobrazuje pri poliach, tak sú aj zoskupené
            // podľa poľa — inak by ich musel triediť prehliadač.
            $issues[$issue['field']][] = [
                'severity' => $issue['severity'],
                'message' => $issue['message'],
                'suggested' => $issue['suggested'],
                'fix' => $issue['fix'],
            ];
        }

        return response()->json(['data' => $issues]);
    }
}
