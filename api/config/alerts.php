<?php

/*
|--------------------------------------------------------------------------
| História upozornení obsluhy (zvonček → História)
|--------------------------------------------------------------------------
|
| Koľko dní sa upozornenia držia. Prečítané sa mažú skôr, neprečítané
| dlhšie, aby nezmizlo nič, čo si ešte nikto nevšimol. Maže
| `notifications:prune` v noci. Samotné objednávky, správy a ponuky
| ostávajú — upozornenie je len odkaz na ne.
|
*/

return [
    'read_days' => (int) env('ALERTS_READ_DAYS', 30),
    'unread_days' => (int) env('ALERTS_UNREAD_DAYS', 90),
];
