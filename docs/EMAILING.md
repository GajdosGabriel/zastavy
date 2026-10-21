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

## Nastavenie

- `.env`: `ACCOUNT_MAILING=true`, voliteľne `ACCOUNT_PUBLIC_URL` (inak `ACCOUNT_URL`).
  Bez `ACCOUNT_MAILING` sa udalosti neposielajú.
- Token zastáv v Accounte potrebuje `mailing:write`, webhook udalosti
  `mailing.delivery.sent`, `mailing.delivery.failed`, `mailing.delivery.bounced`.
- Nový spúšťač: case v `App\Enums\MailingTrigger`, volanie `AccountMailing::event()`
  na mieste udalosti a `php artisan emailing:register-triggers`.
