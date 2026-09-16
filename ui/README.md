# Frontend

Vue 3 + Pinia + Vite. Používajte Node.js 22 a npm; `package-lock.json` je súčasťou repozitára.

```sh
npm ci
cp .env.example .env
npm run dev
```

`.env` nastavuje URL frontendu a API. Hodnoty s prefixom `VITE_` sa dostanú do verejného JavaScriptu; nesmú obsahovať heslá ani súkromné API kľúče.

## Kontroly

```sh
npm run typecheck
npm test
npm run build:ci
```

- `typecheck`: `vue-tsc --noEmit`, strict TypeScript v `src`, vrátane komponentov s `lang="ts"`. Staršie JavaScript moduly zostávajú povolené s `checkJs=false`; nejde o kompletnú migráciu všetkých JS komponentov na TypeScript. Nový kód píšte typovane a neobchádzajte chyby pomocou `@ts-ignore`.
- `test`: Node test runner; ochrana odoslania objednávky, súbežné načítavanie a poradie odpovedí.
- `build:ci`: Vite build bez komunikácie s produkčným API.
- `build`: generovanie sitemap z publikovaného sortimentu + Vite build. Pri nedostupnosti API sa zachová posledná sitemap, ak existuje; pred publikovaním skontrolujte výstup generátora.

Build vzniká v `dist/`. Produkčné URL nastavte v prostredí alebo `.env.production` pred buildom. Statický hosting musí pre neexistujúce súborové cesty vrátiť `index.html`, aby fungovali odkazy Vue Routera. `npm run preview` slúži na kontrolu zostaveného webu, nie ako produkčný server.

## Súbežné požiadavky

Globálny indikátor počíta aktívne HTTP transporty vrátane chýb a zrušení. Zoznam a detail katalógu prijímajú iba najnovšiu odpoveď; staršie odpovede ani chyby neprepíšu nový stav. Úpravy týchto pravidiel musia zachovať testy v `tests/requestLifecycle.test.mjs`.

[Prevádzka a nasadenie](../docs/OPERATIONS.md). Typecheck používa odporúčaný nástroj z [dokumentácie Vue](https://vuejs.org/guide/typescript/overview).
