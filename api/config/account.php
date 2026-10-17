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
|
*/

return [
    'url' => rtrim((string) env('ACCOUNT_URL', 'http://account.local'), '/'),
    'token' => env('ACCOUNT_TOKEN'),
    'webhook_secret' => env('ACCOUNT_WEBHOOK_SECRET'),

    'messaging' => (bool) env('ACCOUNT_MESSAGING', false),

    // Mimo produkcie sa do vzdialenej inštancie Accountu neposiela – mail
    // odoslaný z lokálu cez produkčný Account by dostal skutočný zákazník.
    'allow_remote_writes' => (bool) env('ACCOUNT_ALLOW_REMOTE_WRITES', false),

    'timeout' => (int) env('ACCOUNT_TIMEOUT', 30),
];
