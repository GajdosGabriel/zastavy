<?php

/*
|--------------------------------------------------------------------------
| Následná kontrola údajů zákazníka
|--------------------------------------------------------------------------
|
| Nálezy se do `customer_reviews.issues` ukládají jako klíč a parametry,
| nikdy jako hotová věta — zdůvodnění viz slovenský soubor.
*/

return [
    'fields' => [
        'name' => 'Kontaktní osoba',
        'company' => 'Název firmy',
        'email' => 'E-mail',
        'phone' => 'Telefon',
        'street' => 'Ulice a číslo',
        'postcode' => 'PSČ',
        'city' => 'Město/obec',
        'ico' => 'IČO',
        'dic' => 'DIČ',
        'ic_dic' => 'DIČ (plátce DPH)',
    ],

    'severities' => [
        'error' => 'Chyba',
        'warning' => 'Upozornění',
        'notice' => 'Drobnost',
    ],

    'sources' => [
        'rule' => 'pravidlo',
        'registry' => 'rejstřík',
        'ai' => 'AI',
    ],

    'issues' => [
        'blank_string' => 'Pole je uloženo jako prázdný řetězec, ne jako prázdná hodnota — filtry „nevyplněné" ho proto nenajdou.',
        'blank_required' => 'Pole je prázdné, přestože je povinné.',
        'blank_placeholder' => 'Místo prázdné hodnoty je v poli zástupný znak „:value", který se vytiskne na fakturu.',
        'whitespace' => 'Hodnota má mezery navíc nebo zalomení řádku.',

        'ico_no_digits' => 'IČO neobsahuje ani jednu číslici.',
        'ico_too_long' => 'IČO má více než 8 číslic.',
        'ico_format' => 'IČO není ve tvaru, v jakém ho zná rejstřík — 8 číslic bez mezer a lomítek.',
        'ico_with_text' => 'V poli IČO je kromě čísla i text.',
        'ico_checksum' => 'IČO nesedí na kontrolní číslici — téměř jistě je v něm překlep a v rejstříku neexistuje.',

        'dic_missing' => 'Zákazník má IČO, ale nemá DIČ — na faktuře bude chybět údaj, který rejstřík zná.',
        'dic_format' => 'DIČ obsahuje mezery nebo předponu SK.',
        'dic_with_text' => 'V poli DIČ je kromě čísla i text.',
        'dic_length' => 'DIČ nemá 10 číslic.',
        'dic_checksum' => 'DIČ nesedí na kontrolní součet — pravděpodobně je v něm překlep. Ověřte v rejstříku.',

        'ic_dic_format' => 'DIČ plátce DPH má mezery nebo malá písmena.',
        'ic_dic_shape' => 'DIČ plátce DPH není ve tvaru SK + 10 číslic.',
        'ic_dic_mismatch' => 'DIČ plátce DPH se neshoduje s DIČ — očekávané SK:dic.',

        'phone_unreadable' => 'Telefon nelze přečíst jako slovenské ani zahraniční číslo.',
        'phone_format' => 'Telefon není v mezinárodním tvaru — při exportu a hromadné poště se takový zápis rozpadne.',

        'email_case' => 'E-mail obsahuje velká písmena — při porovnávání s existujícím kontaktem z toho vznikají duplicity.',
        'email_invalid' => 'E-mail není platná adresa — potvrzení objednávky se nedoručí.',

        'postcode_format' => 'PSČ není uloženo jako 5 číslic bez mezer.',
        'postcode_mixed' => 'V PSČ je slepený i jiný údaj — zkontrolujte, co do pole patří.',
        'postcode_length' => 'PSČ nemá 5 číslic.',

        'company_is_number' => 'V názvu firmy je číslo, ne název — vypadá to na IČO zapsané do špatného pole.',
        'company_has_address' => 'V názvu firmy je i adresa — na faktuře se pak vytiskne dvakrát.',

        'street_number_only' => 'V ulici je jen číslo. U obce bez ulic je to v pořádku, ale na obálce chybí název.',

        'registry_tax_mismatch' => 'Neshoduje se s obchodním rejstříkem — ověřte, zda IČO patří tomuto subjektu.',
        'registry_company_differs' => 'Název firmy se liší od úředního znění v rejstříku.',
    ],

    'summary' => [
        'clean' => 'Údaje jsou v pořádku.',
        'found' => 'Nalezeno: :list.',
        'error' => '{1} 1 chyba|[2,4] :count chyby|[5,*] :count chyb',
        'warning' => '{1} 1 upozornění|[2,4] :count upozornění|[5,*] :count upozornění',
        'notice' => '{1} 1 drobnost|[2,4] :count drobnosti|[5,*] :count drobností',
    ],

    'messages' => [
        'disabled' => 'Kontrola je vypnutá konfigurací.',
        'no_review' => 'Zákazník nemá posudek.',
        'run_failed' => 'Kontrolu se nepodařilo dokončit: :error',
        'nothing_reverted' => 'Nebylo co vrátit — hodnotu mezitím změnil někdo jiný.',
    ],

    'mail' => [
        'subject' => 'Kontrola zákazníků — :fixed opravených, :open k prohlédnutí',
        'greeting' => 'Dobrý den,',
        'intro' => 'Následná kontrola prošla :total zákazníků. Níže je, co z toho vyšlo.',
        'fixed_heading' => '**Automaticky opraveno** (formát, prázdné hodnoty, doplněná daňová čísla z rejstříku):',
        'fixed_line' => '• :customer — :field: „:from" → „:to"',
        'open_heading' => '**K prohlédnutí** — tyto změny by měnily význam údaje, tak je necháváme na vás:',
        'open_line' => '• :customer (skóre :score) — :message',
        'more' => '…a dalších :count zákazníků.',
        'action' => 'Otevřít seznam zákazníků',
        'outro' => 'Opravy lze vrátit — původní hodnota je v tomto e-mailu i v detailu zákazníka.',
        'empty' => 'prázdné',
    ],

    'duplicates' => [
        'reason_ico' => 'Stejné IČO :ico',
        'reason_name' => 'Stejný název a město, bez IČO',
        'merged_note' => 'Sloučeno do zákazníka #:id.',
        'nothing_to_merge' => 'Není co sloučit.',
        'merged' => 'Sloučeno :customers záznamů, přesunutých objednávek :orders.',
    ],
];
