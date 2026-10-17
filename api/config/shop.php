<?php

return [

    /*
    | Bankové spojenie obchodu — zobrazí sa zákazníkovi pri platbe prevodom
    | (ďakovná stránka a verejný detail objednávky). Kým IBAN nie je vyplnený,
    | platobné údaje sa nezobrazujú vôbec.
    */
    'bank' => [
        'iban'   => env('SHOP_BANK_IBAN'),
        'swift'  => env('SHOP_BANK_SWIFT'),
        'holder' => env('SHOP_BANK_HOLDER', 'Gajdoš Gabriel - Reprezent'),
    ],

    /*
    | Údaje dodávateľa v hlavičke cenovej ponuky (PDF). Zhodné so stránkou Kontakt.
    */
    'company' => [
        'name'     => env('SHOP_COMPANY_NAME', 'Gajdoš Gabriel - Reprezent'),
        'address'  => env('SHOP_COMPANY_ADDRESS', 'Sekčovská 19, 086 41 Raslavice'),
        'office'   => env('SHOP_COMPANY_OFFICE', 'Slatinská 14, 921 07 Bratislava'),
        'ico'      => env('SHOP_COMPANY_ICO', '14 287 315'),
        'dic'      => env('SHOP_COMPANY_DIC', 'SK1020747398'),
        'phone'    => env('SHOP_COMPANY_PHONE', '0905 320 616'),
        'email'    => env('SHOP_COMPANY_EMAIL', 'obchod@zastavy-vlajky.sk'),
        'web'      => env('SHOP_COMPANY_WEB', 'www.zastavy-vlajky.sk'),
        'register' => env('SHOP_COMPANY_REGISTER'),
    ],

];
