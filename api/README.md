# Backend

## Lokálne spustenie

Vyžaduje PHP 8.3+, Composer 2, rozšírenia `pdo_mysql`, `mbstring`, `dom`, `fileinfo`, `openssl` a databázu MySQL. PHP CLI a webový server musia používať kompatibilnú konfiguráciu. SQLite slúži iba na obmedzený testovací beh.

```sh
composer install
cp .env.example .env
php artisan key:generate
```

V `.env` nastavte DB pripojenie, `APP_URL`, `FRONTEND_URL` (CORS) a úložisko. Pre lokálny vývoj bez S3 nastavte `FILESYSTEM_DISK=public`, `MEDIA_DISK=public`, `MAIL_MAILER=log`. Pre produkciu vyplňte S3 a SMTP údaje podľa prostredia; nepoužívajte testovacie hodnoty.

```sh
php artisan migrate
php artisan storage:link
php artisan serve --host=127.0.0.1 --port=8000
```

`migrate` mení nastavenú databázu. Spúšťajte ho až po overení prostredia a zálohy. Nevytvárajte produkčného administrátora z testovacích fixture ani neimportujte historický export z repozitára ako inštalačný krok.

## Testy

Vytvorte samostatnú prázdnu databázu `zastavy_test` a DB používateľa s právami iba k nej. `phpunit.xml` vyberá toto meno, `APP_ENV=testing`, in-memory cache a nedoručujúci mailer. Host a prihlasovacie údaje dodajte prostredím. Pri inom mene musí názov obsahovať samostatný segment `test` oddelený podčiarkovníkmi. Testovacia aplikácia ho overí ešte pred `RefreshDatabase` a `migrate:fresh`.

Príklad PowerShell:

```powershell
$env:DB_CONNECTION = 'mysql'
$env:DB_HOST = '127.0.0.1'
$env:DB_DATABASE = 'zastavy_test'
$env:DB_USERNAME = 'zastavy_test'
# Heslo dodajte zo správcu tajomstiev alebo lokálneho prostredia.
php vendor/phpunit/phpunit/phpunit --do-not-cache-result
```

Príklad POSIX shellu:

```sh
DB_CONNECTION=mysql DB_DATABASE=zastavy_test DB_USERNAME=zastavy_test php vendor/bin/phpunit --do-not-cache-result
```

- Celá sada vrátane `StockConcurrencyTest` obnovuje testovaciu schému. Nespúšťajte dve celé sady proti tej istej DB naraz.
- `StockConcurrencyTest` spúšťa dva samostatné procesy so spoločnou bariérou; na SQLite sa preskočí.
- `CatalogQueryTest` kontroluje, že počet dotazov na katalóg nerastie s počtom produktov.
- `OperationsHealthTest` overuje zdravý stav, chýbajúce heartbeat signály, backlog a úložisko zlyhaných úloh.
- Pri chybe ochrany prostredia skontrolujte aj cache konfigurácie a `DB_URL`; ochranu nevypínajte.

PHP syntax v POSIX shelli:

```sh
find app bootstrap config database routes tests -name '*.php' -print0 | xargs -0 -n 1 php -l
```

## Prevádzkové príkazy

```sh
php artisan ops:check --json
php artisan queue:failed
php artisan schedule:list
php artisan stocks:audit-returns
```

`ops:check` vracia kód 0 pri zdravom stave a 1 pri probléme. Heartbeat kontroly vyžadujú zapnutý monitor, scheduler a samostatný worker. [Úplný postup nasadenia a reakcie na alarm](../docs/OPERATIONS.md).
