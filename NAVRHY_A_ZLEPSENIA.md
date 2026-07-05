# Návrhy na zlepšenia, chyby a možnosti rozšírenia

Analýza e-shopu zástavy-vlajky.sk (Laravel API `api/` + Vue 3 frontend `ui/`).
Dátum analýzy: 2026-07-05

---

## 🔴 Kritické chyby a bezpečnostné riziká

### 1. ✅ VYRIEŠENÉ — Cena produktu sa preberá z klienta (manipulácia ceny)
**Súbory:** [StoreOrderProduct.php](api/app/Actions/StoreOrderProduct.php:27), [StoreOrder.php](api/app/Actions/StoreOrder.php:68)

Pri verejnom checkoute (`POST /checkouts`) sa cena položky (`active_price`) berie priamo z requestu a nikde sa nevaliduje. Ktokoľvek môže poslať objednávku s cenou `0.01 €` za ľubovoľný produkt. Rovnako sa z klientskej ceny počíta `cartTotal` pre dopravu a zľavu kupónu.

**Riešenie:** Na serveri načítať produkty podľa ID a použiť `Product::getActivePriceAttribute()`. Cenu z requestu ignorovať:
```php
$product = Product::findOrFail($value['id']);
$price = $product->active_price;
```

### 2. ✅ VYRIEŠENÉ — Kupón sa pri vytvorení objednávky neoveruje
**Súbor:** [StoreOrder.php:80-88](api/app/Actions/StoreOrder.php:80)

`CouponController::validate` síce kontroluje `isValid()`, ale `StoreOrder::resolveCheckoutFields()` už nie — stačí poslať `coupon_code` priamo na `POST /checkouts` a zľava sa uplatní aj pre **neaktívny, expirovaný alebo vyčerpaný kupón**, a bez ohľadu na `min_order_price`.

**Riešenie:** V `resolveCheckoutFields()` volať `$coupon->isValid($cartTotal)` a pri nevalidnom kupóne vrátiť 422.

### 3. ✅ VYRIEŠENÉ — Verejný endpoint `/artisan/run`
**Súbor:** [api.php:113-120](api/routes/api.php:113)

Nechránená routa, ktorou ktokoľvek zmaže cache, config a views produkčnej aplikácie (DoS vektor). Navyše obsahuje `dd()`.

**Riešenie:** Odstrániť, alebo presunúť za admin middleware / artisan príkaz.

### 4. Únik osobných údajov cez IČO lookup
**Súbor:** [CheckoutController.php:16-59](api/app/Http/Controllers/Api/CheckoutController.php:16)

`GET /checkouts/{ico}` je verejný a pri zhode v databáze vráti **e-mail, telefón a meno kontaktnej osoby zákazníka** (`customerToCheckoutData`). Útočník môže enumerovať IČO (verejne dostupné) a harvestovať kontakty všetkých zákazníkov → GDPR problém.

**Riešenie:** Verejne vracať len firemné údaje z registra (názov, adresa, DIČ). E-mail/telefón predvyplniť len pre prihláseného používateľa s väzbou na daného zákazníka. Pridať rate limiting.

### 5. ✅ VYRIEŠENÉ — Objednávka cez checkout nie je v DB transakcii
**Súbory:** [StoreCheckout.php](api/app/Actions/StoreCheckout.php), [CheckoutController.php:61](api/app/Http/Controllers/Api/CheckoutController.php:61)

Admin `OrderController::store` transakciu používa, verejný checkout nie. Ak zlyhá vytvorenie order products alebo notifikácia, ostane v DB polovičná objednávka (customer/user/order bez položiek), prípadne inkrementovaný kupón bez objednávky.

**Riešenie:** Obaliť celý flow v `DB::transaction()` a notifikácie posielať až po commite (`DB::afterCommit`).

### 6. ✅ VYRIEŠENÉ — Duplicitné sériové čísla objednávok
**Súbor:** [StoreOrder.php:111-124](api/app/Actions/StoreOrder.php:111)

`serial_number` sa počíta ako `count(id <= aktuálne id)` v danom mesiaci. Keďže `Order` používa `SoftDeletes`, po zmazaní objednávky sa počet zníži a **nová objednávka dostane už existujúce sériové číslo**. Hrozí kolízia aj pri súbežných zápisoch.

**Riešenie:** Samostatná tabuľka čítačov (rok+mesiac → posledné číslo) s `lockForUpdate()`, alebo `withTrashed()` pri počítaní + unique index na `serial_number`.

### 7. Race condition na `used_count` kupónu
**Súbor:** [StoreOrder.php:49-51](api/app/Actions/StoreOrder.php:49)

`usage_limit` sa kontroluje len pri `validate` endpointe, increment nie je atomický s kontrolou → limit sa dá prekročiť súbežnými objednávkami.

**Riešenie:** V transakcii `Coupon::where(...)->lockForUpdate()` + kontrola limitu tesne pred inkrementom.

### 8. Chýbajúca validácia položiek objednávky
**Súbor:** [OrderRequest.php:44-46](api/app/Http/Requests/OrderRequest.php:44)

- `orderProducts.*.id` má len `required` — chýba `exists:products,id` (a kontrola `published`). Dá sa objednať nepublikovaný/zmazaný produkt alebo vyvolať FK chybu.
- `active_price` sa nevaliduje vôbec (viď bod 1).
- Chýba maximum množstva a kontrola `min_order` produktu na serveri (rieši sa len v UI).

### 9. Automatické vytváranie/párovanie používateľov podľa e-mailu
**Súbor:** [CustomerService.php:61-89](api/app/Services/CustomerService.php:61)

Checkout naviaže objednávku na **existujúceho používateľa nájdeného len podľa e-mailu** (vrátane soft-deleted). Ktokoľvek zadá cudzí e-mail a objednávka sa priradí k cudziemu účtu; naopak, novo vytvorený user sa priradí k customerovi bez overenia. Portálový prístup (`DashboardMiddleware` púšťa každého s `customer_id`) potom môže sprístupniť cudzie dáta.

**Riešenie:** Nespájať automaticky podľa e-mailu; vyžadovať verifikáciu e-mailu pred prepojením účtu so zákazníkom/objednávkami.

---

## 🟠 Ďalšie chyby a nedostatky

### 10. Chýba rate limiting na citlivých endpointoch
- `POST /login` — brute-force hesiel (default `throttle:api` 60/min je priveľa).
- `POST /coupons/validate` — verejný, dá sa enumerovať kódy kupónov.
- `GET /checkouts/{ico}` a `POST /checkouts` — spam objednávok / scraping.

**Riešenie:** `->middleware('throttle:5,1')` na login/forgot-password, `throttle:10,1` na kupóny a checkout. Zvážiť honeypot/captcha na checkout.

### 11. Preklep `$filleable` v Product
**Súbor:** [Product.php:25](api/app/Models/Product.php:25) — má byť `$fillable`; momentálne mŕtvy kód, lebo `$guarded = []` povoľuje všetko. Zvážiť explicitný `$fillable` namiesto `$guarded = []` pri všetkých modeloch (obrana pred mass-assignment).

### 12. Placeholder obrázok z Unsplash
**Súbor:** [Product.php:97](api/app/Models/Product.php:97) — fallback thumbnail ťahá externú URL (výkon, súkromie, môže zaniknúť). Nahradiť lokálnym SVG/PNG placeholderom.

### 13. Žiadna práca so skladom pri objednávke
Produkt má `quantity`, ale checkout nekontroluje dostupnosť ani nerezervuje tovar. Sklad sa rieši až pri expedícii (`stocks`). Ak je to zámer (B2B na objednávku), aspoň zobraziť dostupnosť; inak pridať kontrolu/rezerváciu v transakcii.

### 14. `N+1` a výkonové problémy
- [Order.php:167-170](api/app/Models/Order.php:167) — `getStockExpeditionAttribute` robí `$this->stocks()->get()->sum()` (nový query + hydratácia pri každom prístupe; použiť `$this->stocks()->sum('quantity')` alebo `withSum`).
- `$appends = ['productOrderSum']` na Order spôsobí lazy-load `orderProducts` pri každej serializácii.
- Skontrolovať indexy: `orders.uuid` (unique), `orders.serial_number`, `customers.ico`, `products.slug`, `coupons.code`.

### 15. Takmer žiadne testy
`api/tests` obsahuje len example testy + 1 feature test. Minimálne pokryť: checkout happy-path, manipuláciu ceny (po fixe #1), kupóny (expirácia/limit), storno, výpočet `grand_total`, IČO lookup.

### 16. Nekonzistentný jazyk a typovanie v UI
`ui/src/store` mieša `.js` a `.ts` (StoreCustomers.ts vs StoreCheckouts.js), vlastný store pattern namiesto Pinia. Funguje to, ale dlhodobo zvážiť zjednotenie na TypeScript + Pinia (devtools, HMR, testovateľnosť).

### 17. Ceny v košíku môžu byť zastarané
Košík sa drží v `localStorage` vrátane ceny — ak sa cena medzičasom zmení, zákazník odošle starú. Po fixe #1 to prestane byť bezpečnostný problém, ale UX: pri načítaní košíka refreshnúť ceny z API a upozorniť na zmenu.

### 18. `OrderRequest` obsahuje cudziu logiku
[OrderRequest.php:61-67](api/app/Http/Requests/OrderRequest.php:61) — metóda `isFinished()` s update-om modelu nepatrí do FormRequestu. Odstrániť/presunúť.

### 19. Prázdny exception handling
`bootstrap/app.php` — `withExceptions` je prázdny; API by malo mať konzistentné JSON error odpovede a logovanie (napr. Sentry/Flare pre produkciu).

### 20. Queue worker pre notifikácie
Notifikácie sú `ShouldQueue` — overiť, že na produkcii beží worker (`php artisan queue:work` cez supervisor) a `QUEUE_CONNECTION` nie je `sync`. Pridať `failed_jobs` monitoring, inak sa e-maily potichu stratia.

---

## 🟡 Návrhy na zlepšenie (architektúra a kód)

1. **Centralizovaný výpočet košíka** — jedna `CartService`/`PricingService` trieda, ktorú použije `CouponController::validate`, `StoreOrder` aj `PublicOrderController`, aby sa `subtotal/shipping/fee/discount/grand_total` nepočítali na 3 miestach rôzne. Uložiť `grand_total` priamo na order.
2. **DTO namiesto surových polí** — `CustomerService`/`StoreOrder` pracujú s `array` z requestu; `readonly` DTO (napr. `CheckoutData::fromRequest()`) by odstránilo `?? null` reťazce a `normalizeRequest()` hack.
3. **Akcie ako invokable classes** — `StoreCheckout` robí prácu v konštruktore (`__construct` → `handle()`), čo sťažuje testovanie. Zjednotiť na `(new StoreCheckout)->handle($data): Order`.
4. **VAT/DPH na objednávke** — produkt má `vat`, ale `OrderProduct` ukladá len `price/total` bez sadzby DPH. Pre B2B fakturáciu treba uložiť sadzbu DPH v čase objednávky a rozpis DPH v súhrne.
5. **Enum pre typ kupónu** — `'percent'` je magic string v [Coupon.php:47](api/app/Models/Coupon.php:47); použiť PHP enum.
6. **API dokumentácia** — `api/docs` existuje; zvážiť generovanie OpenAPI (Scribe/Scramble).
7. **ORSF fallback** — [CheckoutController::findCompanyByIco](api/app/Http/Controllers/Api/CheckoutController.php:72) závisí od jedného externého API bez cache. Pridať cache (IČO sa nemení často) a graceful degradáciu, keď API nebeží.

---

## 🟢 Možnosti rozšírenia e-shopu

### Predaj a checkout
- **Online platby** — integrácia platobnej brány (GoPay, Besteron, Stripe, TatraPay/CardPay); teraz existujú len metódy s fixným poplatkom.
- **Stav objednávky pre zákazníka** — verejná UUID stránka už existuje; pridať timeline stavu (prijatá → expedovaná → doručená) + e-mail pri zmene stavu s tracking číslom.
- **Fakturácia** — automatické generovanie faktúr (SuperFaktúra/iDoklad API alebo PDF cez dompdf) pri expedícii.
- **Prepojenie na dopravcov** — API Packeta/GLS/SPS: generovanie štítkov, tracking čísla na `shippings`.

### Katalóg a UX
- **Vyhľadávanie a filtrovanie** — fulltext (Laravel Scout + Meilisearch), filtre podľa kategórie, rozmeru, materiálu.
- **Varianty produktov** — vlajky majú typicky rozmery/materiály; teraz je každý rozmer zrejme samostatný produkt. Varianty by zjednodušili katalóg aj správu skladu.
- **B2B cenové hladiny** — množstevné zľavy / individuálne cenníky pre stálych zákazníkov (customer už má vlastnú entitu, je na čom stavať).
- **SEO** — sitemap.xml, meta tagy, štruktúrované dáta (Product schema.org), SSR alebo prerendering (Nuxt/vite-ssg) — SPA bez SSR má slabé SEO pre e-shop.
- **Zoznam obľúbených / opakovaná objednávka** — B2B zákazníci objednávajú opakovane; tlačidlo „objednať znova" z histórie objednávok.

### Administrácia
- **Dashboard s prehľadmi** — tržby po mesiacoch, top produkty, konverzie kupónov (`OrderStatisticsService` už existuje, dá sa rozšíriť).
- **Skladové hospodárstvo** — minimálne stavy, notifikácia pri podlimitnom stave, inventúra.
- **Audit log** — kto zmenil objednávku/cenu/stav (spatie/laravel-activitylog).
- **Export objednávok** — CSV/Excel export objednávok pre účtovníctvo (export používateľov už existuje — zovšeobecniť).

### Prevádzka
- **Monitoring** — Sentry pre PHP aj JS, uptime monitoring, log rotácia.
- **CI/CD** — GitHub Actions: testy + lint (Pint, ESLint) na každý push; teraz sa zrejme nasadzuje ručne.
- **Zálohy DB** — spatie/laravel-backup s notifikáciou pri zlyhaní.

---

## Odporúčané poradie riešenia

| Priorita | Položky |
|----------|---------|
| Ihneď | #1 ceny z klienta, #2 kupón bez validácie, #3 /artisan/run, #4 únik PII cez IČO |
| Tento týždeň | #5 transakcia, #6 sériové čísla, #7–#10 validácie a rate limiting |
| Priebežne | #11–#20 + testy checkout flow |
| Roadmapa | online platby, fakturácia, dopravcovia, vyhľadávanie, SEO |
