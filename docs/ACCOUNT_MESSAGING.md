# E-maily zákazníkom cez Account

E-maily k objednávkam (potvrdenie, zmena, expedícia, správa k objednávke…) môžu
odchádzať cez **Account** namiesto lokálneho SMTP. Account ich pošle zo schránky
`obchod@zastavy-vlajky.sk`, prečíta odpovede zákazníkov (IMAP) a vráti ich sem.
Odpoveď zákazníka sa objaví v konverzácii objednávky, rovnako ako keby ju napísal
cez odkaz na webe. Obsluha ju vidí aj v spoločnom **Inboxe** Accountu, kde sú
konverzácie zo všetkých projektov.

Dnes `obchod@` nikto automaticky nečíta (Reply-To bez spracovania). Po zapnutí sa
odpovede dostanú k objednávke samy.

## Ako to ide

1. `Order::notifyCustomer()` pri zapnutom `ACCOUNT_MESSAGING` neposiela mail
   lokálne, ale zaradí `SendOrderMailViaAccount`.
2. `App\Services\Account\OrderMailer` vyskladá notifikáciu (`toMail()`, šablóny
   ostávajú) a pošle ju do Accountu s referenciou `order:{uuid}` a `thread_id`
   = pôvodné Message-ID objednávky (`OrderThread::id`). Zákazníkovi tak ostane
   jedno vlákno aj so staršími mailmi a odpoveď na starý mail sa spáruje správne.
3. Zápis do histórie e-mailov objednávky (`order_emails`) robí `OrderMailer`.
4. Odpoveď zákazníka → webhook `message.received` → `AccountMessages` vytvorí
   `OrderMessage` (`direction=customer`) aj s prílohami. Medzi neprečítanými
   správami ju uvidíš aj v zastavách.
5. Odpoveď z Inboxu Accountu → `message.sent` (`source=inbox`) → `OrderMessage`
   (`direction=staff`) a záznam „Odpoveď z Inboxu“ v histórii e-mailov.
6. Nedoručené alebo neodoslané → záznam `failed` v histórii e-mailov.

Hromadné kampane (`EmailingService`), reset hesla a pozvánky používateľov
ostávajú lokálne.

## Zapnutie

1. **Account → Projekty:** pridať projekt `zastavy` (ak ešte nie je).
2. **Account → Inbox → Schránky:** schránka `obchod@zastavy-vlajky.sk` s IMAP
   (pri Gmaile heslo aplikácie) a SMTP, označiť ako predvolenú.
3. **Account → API a webhooky:** token pre zastavy s oprávneniami
   `messages:write` a `messages:read`. Webhook `https://…/api/webhooks/account`
   s udalosťami `message.received`, `message.sent`, `message.bounced`, `message.failed`.
4. **zastavy `.env`:**
   ```env
   ACCOUNT_URL=https://account.zastavy-vlajky.sk
   ACCOUNT_TOKEN=acc_…
   ACCOUNT_WEBHOOK_SECRET=whsec_…
   ACCOUNT_MESSAGING=true
   ```
5. `php artisan migrate` (stĺpec `order_messages.account_message_id`).

Mimo produkcie sa do vzdialeného Accountu neposiela bez
`ACCOUNT_ALLOW_REMOTE_WRITES=true`, aby z lokálu neodišiel mail skutočnému zákazníkovi.

## Súbory

| Súbor | Čo robí |
|---|---|
| `api/config/account.php` | URL, token, webhook secret, prepínač |
| `api/app/Services/Account/AccountClient.php` | HTTP klient (odoslanie, konverzácie, odhlásenie) |
| `api/app/Services/Account/OrderMailer.php` | notifikácia → Account, história e-mailov |
| `api/app/Jobs/SendOrderMailViaAccount.php` | fronta s opakovaním |
| `api/app/Services/Account/AccountMessages.php` | webhooky → správy objednávky |
| `api/app/Http/Controllers/Api/AccountWebhookController.php` | overenie podpisu |
| `api/tests/Feature/AccountMessagingTest.php` | testy |
