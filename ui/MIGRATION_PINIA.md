# Migrácia stores na Pinia + TypeScript

Postup a konvencie pre prechod z vlastného „factory" store patternu na Pinia.
Pilot: [`StoreCheckoutOptions.ts`](src/store/StoreCheckoutOptions.ts) (hotový a overený v prehliadači).

---

## Prečo

Súčasný pattern (`reactive()` + exportovaná factory `() => ({ state, ...getters, ...actions })`
+ module-level `watch`) funguje, ale:

- **Žiadne devtools** — nevidno stav/akcie, ťažší debugging.
- **Reaktivita pri destructuringu je krehká** — `getX.value`, ručné `computed`, ľahko sa „stratí".
- **Cross-store závislosti** cez importované factory volania sú neprehľadné.
- **Nekonzistencia** — časť storov je `.js`, časť `.ts`, každý trochu inak.

Pinia dáva devtools, HMR, jasné `state/getters/actions` a čisté typovanie.

---

## Zavedená infraštruktúra (spravené v pilote)

- `pinia` nainštalovaná (v3).
- Zapojená v [`main.js`](src/main.js): `app.use(pinia).use(router)`.
- Pilotný store prevedený, obaja konzumenti upravení, overené v prehliadači (bez chýb konzoly, reaktivita funguje).

---

## Vzor prevodu (starý → Pinia)

| Starý pattern | Pinia |
|---|---|
| `const state = reactive({...})` | `state: () => ({...})` |
| `computed(() => state.x)` | `getters: { x: (s) => s.x }` |
| getter s argumentom, napr. `shippingPrice(total)` | **action/metóda** (getter nesmie brať argument) |
| `actions.foo = () => { state.x = ... }` | `actions: { foo() { this.x = ... } }` |
| `export default () => ({ state, ...getters, ...actions })` | `export const useX = defineStore('x', {...})` |

### Konvencie

1. **Názov**: `export const useXyz = defineStore('xyz', {...})`. Kvôli spätnej kompatibilite
   pri postupnej migrácii ponechaj aj `export default useXyz;` (staré `import useXyz from ...` tak fungujú ďalej).
2. **Pass-through gettery ponechaj** (napr. `getShippingMethods: (s) => s.shippingMethods`),
   ak ich konzumenti používajú — zachová sa verejné API a diff v komponentoch je menší.
3. **Gettery s argumentom → actions.** Pinia getter je cache-ovaná computed bez parametrov.
4. **Typy**: `interface XyzState`, návratové typy getterov a akcií. Projekt nemá `tsconfig`
   (TS transpiluje esbuild bez type-checkingu), takže typy sú dokumentačné — píš ich aj tak.

---

## Úprava konzumentov (dôležité — tu vznikajú regresie)

### Komponenty (`<script setup>`)

```js
import { storeToRefs } from 'pinia';
import { useXyz } from '../../store/StoreXyz';

const store = useXyz();
// State + gettery: cez storeToRefs (inak sa stratí reaktivita)
const { getFoo, someStateField } = storeToRefs(store);
// Akcie: priamo zo store (Pinia ich viaže na inštanciu, destructuring je bezpečný)
const { doThing, shippingPrice } = store;
```

- **State sa NEzanoruje pod `state`.** Staré `state.selectedShippingId` v template → `selectedShippingId`
  (pole zo `storeToRefs`, v template sa auto-unwrapuje).
- V `<script>` majú refy zo `storeToRefs` `.value` (napr. `paymentFee.value`); v template sa unwrapujú samy.

### Iné stores, ktoré store konzumujú

```js
const options = useXyz();          // volať vnútri akcie, nie na module-level
options.selectedShippingId;        // priamy prístup (Pinia unwrapuje, žiadne .value)
options.getCouponCode;             // getter tiež bez .value
options.reset();
```

> Pozn.: `useXyz()` sa musí volať až za behom (po `app.use(pinia)`), typicky vnútri akcie/handlera —
> nie na najvyššej úrovni modulu.

---

## Riziká a na čo dať pozor

- **Module-level `watch`** (napr. [`StoreCheckouts.js`](src/store/StoreCheckouts.js) synchronizuje
  košík a zákazníka do `localStorage`). Pri prevode tohto storu presuň side-effecty buď do akcií,
  alebo do `store.$subscribe(...)` — nerobiť 1:1 kópiu module-level `watch`.
- **Destructuring bez `storeToRefs`** = strata reaktivity. Najčastejšia chyba pri migrácii.
- **Getter s argumentom** ponechaný ako getter = beží, ale nie je reaktívne cache-ovaný podľa argumentu → daj ho ako action.
- Po prevode `.js` → `.ts` **zmaž pôvodný `.js`**, inak import je nejednoznačný (kolízia `.js`/`.ts`).

---

## Odporúčané poradie (od najmenej závislých po najviac)

Migruj po jednom store + jeho konzumentov, každý samostatne otestuj v prehliadači.

> **Poradie podľa blast radius** (počet konzumentov): migruj najprv stores s málo konzumentmi.
> Vysoký blast radius (`StoreErrors` 31, `StoreQuery` 15, `StoreLoading` 13, `StorePaginator` 8)
> nechaj na koniec — ich migrácia si vynúti úpravu desiatok súborov naraz.

1. ✅ **StoreCheckoutOptions** — hotové (pilot, overené v prehliadači).
2. ✅ **StoreShippingMethods**, ✅ **StorePaymentMethods** — hotové (overené `vite build`).
3. ✅ **StoreCoupons**, ✅ **StoreNotices**, ✅ **StoreUserExport** — hotové (overené `vite build`).
   ✅ **StoreCategories**, ✅ **StoreReturns**, ✅ **StoreStocks**, ✅ **StoreAnnouncements** — hotové
   (overené `vite build`; StoreAnnouncements aj runtime v prehliadači cez verejný banner).
   ⏸️ **StoreNavigation odložené** — konzumuje ho `StoreUsers.js` na **module-level**
   (`useNavigation()` mimo akcie), spadlo by pred `app.use(pinia)`. Migruj až so `StoreUsers`.
   ⏸️ **StoreImages odložené** — volajú ho `StoreProducts.ts` aj `StoreHome.ts` na **module-level**
   (`useImages()` mimo akcie). Migruj až s nimi.
4. ✅ **StoreHome**, ✅ **StoreAdminUsers**, ✅ **StoreProducts** — hotové (StoreHome + StoreProducts
   overené runtime v prehliadači proti backendu). `StoreProducts` je **setup-store** (má vlastný `watch`
   na `product` → v options-store sa nedá; setup-store to umožňuje). Migráciou Products+Home sa
   **odblokoval `StoreImages`** (jeho jediní module-level volajúci sú už Pinia).
   ✅ **StoreImages**, ✅ **StoreShippings**, ✅ **StoreUsers** — hotové (StoreUsers overený runtime:
   boot + router guard + login page bez chýb; 16 konzumentov vrátane router guardu a `filterLabels.js`).
   Tým sa **odblokoval `StoreNavigation`** (jediný module-level volajúci bol StoreUsers, teraz volá
   `useNavigation()` vnútri akcií). Ďalej `StoreNavigation`, `StoreCustomers`, `StoreOrders`, `StoreOrderProducts`.
   > **Router guard / plain moduly:** `getUser.value` → `store.getUser` (getter, bez `.value`);
   > module-level `useX()` v ne-store moduloch (napr. `filterLabels.js`) presunúť do lazy getterov.
   > Postup: presuň **vlastné** module-level `useX()` volania (util stores) dovnútra akcií/getterov;
   > util stores (Errors/Query/Paginator/Images) ostávajú staré, volanie z Pinia akcie je OK.
   > **Ak má store module-level `watch`/side-effect → použi setup-store syntax** (`defineStore('x', () => {...})`).
5. Vysoký blast radius (na koniec): `StorePaginator`, `StoreLoading`, `StoreQuery`, `StoreErrors`.
6. **Naposledy `StoreCheckouts`** — má module-level `watch` + závisí na Customers/Errors/CheckoutOptions;
   preveď až keď sú závislosti hotové.

> **`StoreValidations`** má 0 konzumentov — de facto mŕtvy kód. Nemigrovať; kandidát na zmazanie (samostatne).

> **Lekcia 1 (potvrdená):** pred migráciou storu skontroluj, či ho iný **nemigrovaný store**
> nevolá na module-level — grepni **`.js` aj `.ts`** stores (`grep -rn "useX()" src/store/`).
> Ak áno, odlož ho za onen store — Pinia `useX()` mimo runtime spadne.
> Takto sú odložené `StoreNavigation` (volá `StoreUsers.js`) a `StoreImages` (volajú `StoreProducts.ts`, `StoreHome.ts`).
> Konzumenti-**komponenty** sú vždy OK (setup beží za behom), aj volania vnútri event-handlerov/akcií.

> **Lekcia 2:** Pinia **getter nesmie mať rovnaký názov ako state pole** (kolízia). Napr. pôvodný
> `StoreCategories` mal getter `categories` aj state `categories` → getter sa vypustil a konzumenti
> berú state pole priamo cez `storeToRefs`.

> **Lekcia 3:** kolízia „vlastná akcia vs. importovaná funkcia rovnakého mena" (napr. `setPaginator`
> v `StoreStocks`/`StoreAnnouncements` — vlastná akcia aj `setPaginator` zo `StorePaginator`).
> Rieš explicitným volaním vnútri akcie: `usePaginator().setPaginator(...)`.

> **Pozn. (mimo migrácie):** `src/components/stock/FilterPanel.vue` volá `fetchSearchInput` zo
> `StoreStocks`, ktorá **neexistuje** (undefined už v pôvodnom kóde) — latentný bug, správanie
> ponechané 1:1. Opraviť samostatne.

---

## Definícia hotového (per store)

- [ ] `defineStore` + typy (`interface ...State`, návratové typy).
- [ ] Všetci konzumenti upravení (`storeToRefs` pre state/gettery, akcie zo store).
- [ ] Pôvodný `.js` zmazaný.
- [ ] Overené v prehliadači: bez chýb konzoly, reaktivita a akcie fungujú.
