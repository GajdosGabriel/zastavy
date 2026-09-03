<?php

/*
|--------------------------------------------------------------------------
| Post-kontrola údajov zákazníka
|--------------------------------------------------------------------------
|
| Nálezy sa do `customer_reviews.issues` ukladajú ako kľúč a parametre, nie
| ako hotová veta. Dôvod je praktický: posudok vznikne v nočnom behu, kde
| žiadny používateľ nie je, a čítať ho bude admin vo svojom jazyku aj e-mail
| poslaný niekomu inému. Keby sa text preložil pri zápise, zamrzol by
| v jazyku, ktorý mal server práve nastavený.
|
| `fields` sú tu raz pre všetkých: pre panel v administrácii, pre súhrn
| v e-maile aj pre výpis v konzole.
*/

return [
    'fields' => [
        'name' => 'Kontaktná osoba',
        'company' => 'Názov firmy',
        'email' => 'E-mail',
        'phone' => 'Telefón',
        'street' => 'Ulica a číslo',
        'postcode' => 'PSČ',
        'city' => 'Mesto/obec',
        'ico' => 'IČO',
        'dic' => 'DIČ',
        'ic_dic' => 'IČ DPH',
    ],

    'severities' => [
        'error' => 'Chyba',
        'warning' => 'Upozornenie',
        'notice' => 'Drobnosť',
    ],

    'sources' => [
        'rule' => 'pravidlo',
        'registry' => 'register',
        'ai' => 'AI',
    ],

    'issues' => [
        'blank_string' => 'Pole je uložené ako prázdny reťazec, nie ako prázdna hodnota — filtre „nevyplnené" ho preto nenájdu.',
        'blank_required' => 'Pole je prázdne, hoci je povinné.',
        'blank_placeholder' => 'Namiesto prázdnej hodnoty je v poli zástupný znak „:value", ktorý sa vytlačí na faktúru.',
        'whitespace' => 'Hodnota má medzery navyše alebo zalomenie riadku.',

        'ico_no_digits' => 'IČO neobsahuje ani jednu číslicu.',
        'ico_too_long' => 'IČO má viac než 8 číslic.',
        'ico_format' => 'IČO nie je v tvare, v akom ho pozná register — 8 číslic bez medzier a lomítok.',
        'ico_with_text' => 'V poli IČO je okrem čísla aj text.',
        'ico_checksum' => 'IČO nesedí na kontrolnú číslicu — takmer isto je v ňom preklep a v registri neexistuje.',

        'dic_missing' => 'Zákazník má IČO, ale nemá DIČ — na faktúre bude chýbať údaj, ktorý register pozná.',
        'dic_format' => 'DIČ obsahuje medzery alebo predponu SK.',
        'dic_with_text' => 'V poli DIČ je okrem čísla aj text.',
        'dic_length' => 'DIČ nemá 10 číslic.',
        'dic_checksum' => 'DIČ nesedí na kontrolný súčet — pravdepodobne je v ňom preklep. Overte v registri.',

        'ic_dic_format' => 'IČ DPH má medzery alebo malé písmená.',
        'ic_dic_shape' => 'IČ DPH nie je v tvare SK + 10 číslic.',
        'ic_dic_mismatch' => 'IČ DPH sa nezhoduje s DIČ — očakávané SK:dic.',

        'phone_unreadable' => 'Telefón sa nedá prečítať ako slovenské ani zahraničné číslo.',
        'phone_format' => 'Telefón nie je v medzinárodnom tvare — pri exporte a hromadnej pošte sa taký zápis rozpadne.',

        'email_case' => 'E-mail obsahuje veľké písmená — pri porovnávaní s existujúcim kontaktom z toho vznikajú duplicity.',
        'email_invalid' => 'E-mail nie je platná adresa — potvrdenie objednávky sa nedoručí.',

        'postcode_format' => 'PSČ nie je uložené ako 5 číslic bez medzier.',
        'postcode_mixed' => 'V PSČ je zlepený aj iný údaj — skontrolujte, čo do poľa patrí.',
        'postcode_length' => 'PSČ nemá 5 číslic.',

        'company_is_number' => 'V názve firmy je číslo, nie názov — vyzerá to na IČO zapísané do zlého poľa.',
        'company_has_address' => 'V názve firmy je aj adresa — na faktúre sa potom vytlačí dvakrát.',

        'street_number_only' => 'V ulici je iba číslo. Pri obci bez ulíc je to v poriadku, ale na obálke chýba názov.',

        'registry_tax_mismatch' => 'Nezhoduje sa s obchodným registrom — overte, či IČO patrí tomuto subjektu.',
        'registry_company_differs' => 'Názov firmy sa líši od úradného znenia v registri.',
    ],

    'summary' => [
        'clean' => 'Údaje sú v poriadku.',
        'found' => 'Nájdené: :list.',
        'error' => '{1} 1 chyba|[2,4] :count chyby|[5,*] :count chýb',
        'warning' => '{1} 1 upozornenie|[2,4] :count upozornenia|[5,*] :count upozornení',
        'notice' => '{1} 1 drobnosť|[2,4] :count drobnosti|[5,*] :count drobností',
    ],

    'messages' => [
        'disabled' => 'Kontrola je vypnutá konfiguráciou.',
        'no_review' => 'Zákazník nemá posudok.',
        'run_failed' => 'Kontrolu sa nepodarilo dokončiť: :error',
        'nothing_reverted' => 'Nebolo čo vrátiť — hodnotu medzitým zmenil niekto iný.',
    ],

    'mail' => [
        'subject' => 'Kontrola zákazníkov — :fixed opravených, :open na pozretie',
        'greeting' => 'Dobrý deň,',
        'intro' => 'Post-kontrola prešla :total zákazníkov. Nižšie je, čo z toho vyšlo.',
        'fixed_heading' => '**Automaticky opravené** (formát, prázdne hodnoty, doplnené daňové čísla z registra):',
        'fixed_line' => '• :customer — :field: „:from" → „:to"',
        'open_heading' => '**Na pozretie** — tieto zmeny by menili význam údaja, tak ich nechávame na vás:',
        'open_line' => '• :customer (skóre :score) — :message',
        'more' => '…a ďalších :count zákazníkov.',
        'action' => 'Otvoriť zoznam zákazníkov',
        'outro' => 'Opravy sa dajú vrátiť ručne — pôvodná hodnota je v tomto e-maile aj v detaile zákazníka.',
        'empty' => 'prázdne',
    ],

    'duplicates' => [
        'reason_ico' => 'Rovnaké IČO :ico',
        'reason_name' => 'Rovnaký názov a mesto, bez IČO',
        'merged_note' => 'Zlúčené do zákazníka #:id.',
        'nothing_to_merge' => 'Nie je čo zlúčiť.',
        'merged' => 'Zlúčené :customers záznamov, presunutých objednávok :orders.',
    ],
];
