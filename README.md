# Zástavy a vlajky

B2B e-shop a interná správa objednávok, variantov, skladu, expedícií a vratiek.

- `api/`: Laravel 13, PHP 8.3+, databáza MySQL, bearer tokeny Sanctum.
- `ui/`: Vue 3, Pinia, Vite; pre lokálny vývoj a CI používajte Node.js 22.
- [Backend – inštalácia a testy](api/README.md)
- [Frontend – vývoj a kontroly](ui/README.md)
- [Nasadenie, fronta, monitoring a obnova](docs/OPERATIONS.md)
- [Analýza a dokončené kroky](ANALYZA-PROJEKTU-2026-09-14.md)

## Kontroly pred odovzdaním

Backend spúšťajte iba proti samostatnej testovacej databáze, napr. `zastavy_test`:

```sh
cd api
composer install
composer validate --strict --no-check-publish
php vendor/bin/phpunit --do-not-cache-result
```

`phpunit.xml` nastavuje `APP_ENV=testing` a `DB_DATABASE=zastavy_test`; host, používateľa a heslo nastavte na testovaciu inštanciu. Testy obnovujú schému. Ochrana pred začiatkom testu odmietne databázu bez samostatného segmentu `test` v názve a prostredie odlišné od `testing`. Samotný názov nie je náhradou izolácie: testovací používateľ nesmie mať práva na produkčnú databázu. Súbehové testy potrebujú MySQL a možnosť spustiť samostatné PHP procesy; SQLite `:memory:` ich preskočí.

```sh
cd ui
npm ci
npm run typecheck
npm test
npm run build:ci
```

Workflow `.github/workflows/ci.yml` spúšťa rovnaké kontroly pri pushi, pull requeste alebo ručne. Backend používa izolovaný MySQL 8.4 kontajner. `build:ci` nevolá produkčné API; produkčný `npm run build` navyše generuje sitemap.

Neukladajte heslá, exporty zákazníkov ani zálohy databázy do Gitu. Existujúci historický SQL export v repozitári sa týmto krokom nemenil; posúdenie jeho obsahu a prípadné čistenie histórie je samostatná operácia.
