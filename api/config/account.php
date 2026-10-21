<?php

/*
|--------------------------------------------------------------------------
| Napojenie na Account – komunikácia so zákazníkmi
|--------------------------------------------------------------------------
|
| Account posiela e-maily k objednávkam zo schránky obchod@, číta odpovede
| zákazníkov (IMAP) a vracia ich sem webhookom – správa zákazníka sa tak
| objaví v konverzácii objednávky rovnako, ako keby ju napísal na webe.
| Operátor ich číta a odpovedá v spoločnom Inboxe Accountu.
|
| Bez `ACCOUNT_TOKEN` alebo s `ACCOUNT_MESSAGING=false` posielajú zastavy
| maily ako doteraz cez lokálne SMTP.
|
|   ACCOUNT_URL=https://account.zastavy-vlajky.sk
|   ACCOUNT_TOKEN=acc_xxxxxxxxxxxxxxxx      (oprávnenia messages:write, messages:read)
|   ACCOUNT_WEBHOOK_SECRET=whsec_xxxxxxxx    (webhook /api/webhooks/account, udalosti message.*)
|   ACCOUNT_MESSAGING=true
|   ACCOUNT_MAILING=true                     (kampane v Accounte, oprávnenie mailing:write)
|
*/

return [
    'url' => rtrim((string) env('ACCOUNT_URL', 'http://account.local'), '/'),
    'token' => env('ACCOUNT_TOKEN'),
    'webhook_secret' => env('ACCOUNT_WEBHOOK_SECRET'),

    'messaging' => (bool) env('ACCOUNT_MESSAGING', false),

    // Emailing (kampane) v Accounte: udalosti objednávok idú tam a lokálny
    // modul Emailing neposiela. Token potrebuje aj mailing:write.
    'mailing' => (bool) env('ACCOUNT_MAILING', false),

    // Verejná adresa Accountu pre odkazy v už odoslaných kampaniach
    // (odhlásenie, klik, otvorenie) – ACCOUNT_URL môže byť interná.
    'public_url' => rtrim((string) env('ACCOUNT_PUBLIC_URL', env('ACCOUNT_URL', 'http://account.local')), '/'),

    // Mimo produkcie sa do vzdialenej inštancie Accountu neposiela – mail
    // odoslaný z lokálu cez produkčný Account by dostal skutočný zákazník.
    'allow_remote_writes' => (bool) env('ACCOUNT_ALLOW_REMOTE_WRITES', false),

    'timeout' => (int) env('ACCOUNT_TIMEOUT', 30),

    // Objednávky z e-mailu: nový mail mimo vlákna objednávky (webhook
    // message.unassigned) rozpozná AI a pripraví návrh na schválenie.
    // `auto_create` z úplného návrhu rovno vytvorí objednávku; doprava
    // a platba sa vtedy berú odtiaľto (bez nich ostane návrh na doplnenie).
    'email_orders' => [
        'enabled' => (bool) env('EMAIL_ORDERS', false),
        'auto_create' => (bool) env('EMAIL_ORDERS_AUTO_CREATE', false),
        'shipping_method_id' => env('EMAIL_ORDERS_SHIPPING_METHOD_ID') ? (int) env('EMAIL_ORDERS_SHIPPING_METHOD_ID') : null,
        'payment_method_id' => env('EMAIL_ORDERS_PAYMENT_METHOD_ID') ? (int) env('EMAIL_ORDERS_PAYMENT_METHOD_ID') : null,
    ],
];
