<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Post-kontrola údajov zákazníka
    |--------------------------------------------------------------------------
    |
    | Zákazník vzniká z checkoutu, teda z toho, čo si do formulára napísal
    | človek, ktorý objednáva zástavu — nie účtovníčka. V tabuľke `customers`
    | preto sedí IČO v poli „Názov firmy", adresa vlepená do názvu, DIČ ako
    | prázdny reťazec, IČ DPH ako pomlčka a telefón s lomítkom. Tieto údaje
    | idú na faktúru.
    |
    | Kontrola beží AŽ PO uložení, v troch vrstvách od najlacnejšej:
    |
    |   1. pravidlá  — formát a zjavné hlúposti (CustomerDataRules); zadarmo
    |   2. register  — porovnanie s ORSF podľa IČO (CompanyRegistry); autorita
    |   3. AI        — to, na čo pravidlo napísať nevieme: veľké písmená
    |                  v mene, preklepy, meno osoby v poli firmy
    |
    | Opraviť sa smie iba to, čo NEMENÍ význam údaja (whitespace, „-" → null,
    | telefón do +421 tvaru). Názov firmy ani adresu nikdy neprepisujeme sami —
    | to by bola tichá zmena fakturačných údajov. Tie sa iba navrhnú a admin
    | ich potvrdí jedným klikom v detaile zákazníka.
    */

    /*
     * Vypnutím prestane kontrola vznikať aj bežať. Existujúce riadky ostanú
     * a po zapnutí sa dobehnú — výber si berie všetko, čo je splatné.
     */
    'enabled' => (bool) env('CUSTOMER_REVIEW_ENABLED', true),

    /*
     * Koľko zákazníkov sa skontroluje v jednom behu príkazu. Každý riadok je
     * až jedno volanie registra a jedno volanie OpenAI, teda sekundy.
     */
    'batch' => (int) env('CUSTOMER_REVIEW_BATCH', 10),

    /*
     * Koľko minút po uložení sa čaká. Odklad nie je technický — je to
     * slušnosť: po checkoute ešte admin údaje dolaďuje a e-mail o chybe,
     * ktorú medzitým sám opravil, je horší než žiadny. Každé ďalšie uloženie
     * posúva odklad odznova.
     */
    'delay_minutes' => (int) env('CUSTOMER_REVIEW_DELAY_MINUTES', 15),

    /*
     * Doplnenie chýbajúceho DIČ / IČ DPH / názvu z obchodného registra.
     * Vypnite, keď api.orsf.sk vypadne — kontrola pobeží ďalej bez neho.
     */
    'registry' => (bool) env('CUSTOMER_REVIEW_REGISTRY', true),

    /*
     * Volanie AI. Vypnutím ostanú vrstvy 1 a 2 — tie stoja za väčšinu nálezov
     * a nestoja nič.
     */
    'ai' => (bool) env('CUSTOMER_REVIEW_AI', true),

    /*
     * Model. Kontrola je čítanie desiatich krátkych polí, nie tvorba.
     */
    'model' => env('CUSTOMER_REVIEW_MODEL', 'gpt-4o-mini'),

    /*
     * Ktoré nálezy sa smú opraviť samé. Zoznam je zámerne konečný a zámerne
     * neobsahuje nič, čo mení význam údaja — pribudnúť sem smie len oprava,
     * po ktorej má pole tú istú hodnotu, len čitateľnú.
     *
     *   trim              — orezanie medzier a dvojitých medzier
     *   blank_to_null     — "" a "-" a "." → NULL (prázdne pole nie je hodnota)
     *   phone_format      — 0905/123 456 → +421905123456
     *   ico_pad           — 307181 → 00307181 (register používa 8 miest)
     *   postcode_format   — "05201 " → 05201
     *   registry_tax_ids  — doplnenie CHÝBAJÚCEHO DIČ / IČ DPH z registra
     *                       (nikdy neprepisuje vyplnené — len dopĺňa prázdne)
     */
    'autofix' => [
        'trim',
        'blank_to_null',
        'phone_format',
        'ico_pad',
        'postcode_format',
        'registry_tax_ids',
    ],

    /*
     * Komu chodí súhrn. Prázdne = všetkým používateľom s rolou nižšie.
     * Do CUSTOMER_REVIEW_NOTIFY sa dá dať zoznam e-mailov oddelený čiarkou.
     */
    'notify_emails' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CUSTOMER_REVIEW_NOTIFY', ''))
    ))),

    'notify_roles' => ['super-admin', 'admin'],

    /*
     * Súhrn sa posiela na konci behu príkazu, nie po každom zákazníkovi.
     * Pri prvom prechode 1 955 existujúcich záznamov by inak z pošty admina
     * ostala spálená zem.
     */
    'digest' => [
        /* Neposielať súhrn, kým nie je aspoň toľko zákazníkov s nálezom. */
        'min_records' => (int) env('CUSTOMER_REVIEW_DIGEST_MIN', 1),

        /* Koľko zákazníkov sa v e-maile vypíše; zvyšok je len počet. */
        'max_records' => 15,
    ],
];
