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

];
