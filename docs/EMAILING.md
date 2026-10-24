# Emailing

Kampane, šablóny, príjemcovia a automatizácie po objednávke bežia v **Accounte**
(menu Emailing, projekt zastavy). Zastavy vlastný emailing nemajú – prechod
prebehol 21. 10. 2026 (`mailing:import` v Accounte, potom sa lokálne tabuľky
`mailing_*` zrušili).

## Čo robia zastavy

- **Hlásia udalosti objednávok** (`AccountMailing::event`, fronta
  `ReportMailingEvent`): vytvorená objednávka, expedovaná zásielka, vybavená
  objednávka, storno. Spolu s udalosťou idú premenné `cislo_objednavky`, `firma`,
  `odkaz_objednavky` a vlákno objednávky, aby odpoveď vo vlákne citovala potvrdenie.
- **Rušia čakajúce maily** pri storne alebo zmazaní objednávky (`AccountMailing::cancel`).
- **Zapisujú odpoveď vo vlákne do histórie objednávky** z webhooku
  `mailing.delivery.sent|failed|bounced` (záznam typu „Emailing“).
- **Presmerujú staré odkazy** z kampaní odoslaných ešte zo zastáv
  (`/api/emailing/unsubscribe|link|image/{token}`) do Accountu s tým istým tokenom.
- **Udržiavajú adresár zákazníkov** v Accounte (`AccountMailing::syncContact`,
  fronta `SyncMailingContact`). Každý e-mail zákazníka je kontakt so štítkami
  `customer` (má objednávku okrem konceptu a storna), typ zákazníka
  (`municipality`, `school`, `company`, `person`) a `account` (má prihlásenie).
  Atribúty `{meno}` a `{firma}` sa dajú použiť v texte kampane. Zákazníci dávajú
  súhlas s ponukami pri objednávke, preto ide kontakt so súhlasom. Stav sa pošle
  pri zmene zákazníka, objednávky alebo účtu. Keď adresa už nepatrí žiadnemu
  zákazníkovi, kontakt sa v Accounte zmaže. Odhlásenie eviduje Account a sync
  ho nezruší.

## Nastavenie

- `.env`: `ACCOUNT_MAILING=true`, voliteľne `ACCOUNT_PUBLIC_URL` (inak `ACCOUNT_URL`).
  Bez `ACCOUNT_MAILING` sa udalosti neposielajú.
- Token zastáv v Accounte potrebuje `mailing:write`, webhook udalosti
  `mailing.delivery.sent`, `mailing.delivery.failed`, `mailing.delivery.bounced`.
- Nový spúšťač: case v `App\Enums\MailingTrigger`, volanie `AccountMailing::event()`
  na mieste udalosti a `php artisan emailing:register-triggers`.
- Po nasadení: `php artisan emailing:register-triggers` (udalosti aj štítky),
  prvýkrát aj `php artisan account:sync-mailing-contacts --dry-run` a potom bez
  `--dry-run`. Ten jednorazovo doplní všetkých existujúcich zákazníkov.
