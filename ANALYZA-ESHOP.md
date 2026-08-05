# Analýza: cesta od interného objednávkového systému k modernému e-shopu

**Projekt:** zastavy-vlajky.sk — Laravel 13 API (`api/`) + Vue 3 SPA (`ui/`)
**Dátum:** 4. 8. 2026
**Zameranie:** chýbajúce e-shop funkcie · technika a výkon · admin a prevádzka

---

## Zhrnutie

Kódová báza je na dobrej úrovni ako **B2B objednávkový a expedičný systém**: čistá doménová
vrstva (Actions/Services/Filters/Resources), Spatie permissions, prepracovaný životný cyklus
objednávky (`OrderStatus` so 7 stavmi vrátane čiastočnej expedície), vratky, kupóny,
variantová taxonómia s fasetovým filtrom, SEO modul s JSON-LD a generovaná sitemap.
To je viac, než má priemerný slovenský e-shop.

**Ako verejný e-shop tam ale chýbajú základné piliere.** Zákazník nemôže zaplatiť online,
nedostane faktúru, nemá zákaznícky účet, nevie si prezerať kategórie, nevie vyhľadávať,
nevidí recenzie a nemá výber výdajného miesta. Prevádzka nemá analytiku, feedy ani
napojenie na dopravcu či účtovníctvo. Technicky je najväčšou brzdou SPA bez SSR/prerenderu.

Poradie práce v tomto dokumente: **najprv odstrániť blokátory (fáza 0), potom konverzia
(fáza 1), potom B2B diferenciácia (fáza 2), potom rast (fáza 3).** Priebežné technické
dlhy sú v samostatnej sekcii.

---

## 1. Chýbajúce e-shop funkcie

### 1.1 Online platba — kritické

`payment_methods` má `enum('card','bank_transfer','cash_on_delivery')`, ale v celom repo
nie je ani jedna platobná brána (grep na `stripe|gopay|comgate|besteron|trustpay|barion`
vracia nulu). Typ `card` je dnes iba štítok — objednávka sa uloží, pošle sa e-mail a tým
to končí. Žiadny model platby, žiadny stav `paid`, žiadny webhook, žiadna refundácia.

**Čo doplniť**

- Tabuľka `payments`: `order_id`, `gateway`, `gateway_payment_id`, `amount`, `currency`,
  `status` (pending/authorized/paid/failed/refunded), `paid_at`, `raw_payload` (json).
- Brána — pre SK trh reálne: **Comgate**, **Besteron**, **GoPay**, alebo **Stripe**
  (karty + Apple/Google Pay, najlepšie DX). Odporúčanie: Stripe pre B2C karty +
  bankový prevod s VS pre obce a školy, ktoré aj tak platia z rozpočtu.
- Webhook endpoint s overením podpisu, idempotenciou a záznamom do `payments`.
- Nový stav objednávky `AwaitingPayment` v `App\Enums\OrderStatus` + prechody.
- Notifikácie `PaymentReceived` a `PaymentReminder` (nezaplatený prevod po 3/7 dňoch).
- Refundácia naviazaná na existujúci `OrderReturn` — dnes sa vratka vybaví, ale peniaze
  sa nikde nevracajú programovo.

**Súvisiace súbory:** `api/app/Models/PaymentMethod.php`, `api/app/Enums/OrderStatus.php`,
`api/app/Actions/StoreCheckout.php`, `ui/src/components/checkout/CartIndex.vue`.

---

### 1.2 Faktúry a doklady — kritické

Nikde nie je model faktúry ani generovanie PDF. Zákazník po objednávke dostane e-mail
a URL `/objednavka/{uuid}` — ale nie doklad. Pre obce, školy a firmy (deklarovaná cieľová
skupina) je faktúra s náležitosťami podľa zákona o DPH podmienka nákupu, nie nadštandard.

**Čo doplniť**

- Tabuľka `invoices`: číselný rad, dátum vystavenia / dodania / splatnosti, VS, KS, ŠS,
  odberateľ (snapshot údajov, nie FK — údaje sa nesmú meniť spätne), rozpis DPH podľa sadzieb.
- Generovanie PDF (`spatie/laravel-pdf` alebo `barryvdh/laravel-dompdf`) + uloženie do
  `storage/app/invoices/`, prístupné cez signed URL.
- Zálohová faktúra / proforma pre platbu prevodom vopred.
- Dodací list a štítok na balík naviazaný na existujúci `Shipping` model.
- Dobropis pri vybavení `OrderReturn`.

---

### 1.3 Zákaznícky účet

Login a registrácia existujú (`ui/src/components/auth/`), ale prihlásený zákazník nemá kam ísť —
v `ui/src/router/index.js` nie sú žiadne `public.account.*` routy. Verejná časť pozná len
`/objednavka/{uuid}` cez `PublicOrderController`.

**Čo doplniť**

- `/moj-ucet` — prehľad, `/moj-ucet/objednavky`, `/moj-ucet/udaje`, `/moj-ucet/adresy`.
- **Opakovaná objednávka** ("objednať znova z objednávky č. X") — pri obciach, ktoré každý
  rok dokupujú tie isté vlajky, je to najsilnejšia funkcia z celého zoznamu.
- História faktúr na stiahnutie.
- Uložené doručovacie adresy (dnes ich `Customer` drží len jednu).
- Voliteľne: sledovanie zásielky priamo v účte.

---

### 1.4 Kategórie v storefronte

`Category` model, pivot na `Product` aj `ProductFilter::byCategory()` existujú — ale
**verejný web ich nikde nezobrazuje**. Navigácia (`ui/src/components/layout/navigationMain.vue`)
ich nemá, neexistuje routa `/kategoria/{slug}` a v `sitemap.xml` (`ui/scripts/generate-sitemap.mjs`)
sú len 4 statické stránky + produkty.

Znamená to, že sa vzdávate desiatok kategóriových landing pages — a práve tie zvyknú v tomto
segmente ťahať väčšinu organickej návštevnosti („obecné zástavy", „štátna vlajka SR",
„smútočná vlajka", „stožiare a držiaky").

**Čo doplniť**

- Verejná routa `/kategoria/{slug}` s vlastným `<h1>`, popisom, breadcrumbs (+ `BreadcrumbList` JSON-LD).
- Kategórie do hlavnej navigácie a do sitemap.
- Verejný endpoint pre strom kategórií (dnes je `CategoryController` len pod `AdminMiddleware`).
- Kanonizácia URL a `noindex` na kombinácie fasiet, aby nevznikol duplicitný obsah
  (`INDEXABLE_ROUTES` v `ui/src/models/seo.js` sa musí rozšíriť premyslene, nie plošne).

---

### 1.5 Vyhľadávanie

`ProductFilter::bySearchInput()` robí `LIKE '%výraz%'` cez `code`, `name`, `description`
a varianty. Vo verejnom katalógu nie je vyhľadávacie pole vôbec.

Problémy: `%...%` nikdy nepoužije index (full table scan), nezvláda diakritiku ani preklepy
(„zastava" vs. „zástava"), nemá relevanciu ani našepkávač.

**Čo doplniť**

- Krátkodobo: MySQL `FULLTEXT` index nad `name` + `description` a normalizácia diakritiky.
- Cieľovo: **Laravel Scout + Meilisearch** (alebo Typesense) — typo tolerancia, fasety,
  synonymá, našepkávač do 20 ms. Pri vašom objeme je Meilisearch v Dockeri triviálny.
- Vyhľadávacie pole v hlavičke s okamžitým dropdownom (produkt, obrázok, cena, dostupnosť).
- Logovať dopyty bez výsledku — je to najlacnejší zdroj informácie, čo v katalógu chýba.

---

### 1.6 Doprava a dopravcovia

`ShippingMethod` je jednoduchý číselník, `ShippingService` rieši len internú expedíciu
(rozpad na `Stock` položky). Chýba akékoľvek napojenie na dopravcu.

**Čo doplniť**

- **Packeta (Zásielkovňa)** widget na výber výdajného miesta priamo v košíku — dnes ho
  očakáva prakticky každý slovenský zákazník.
- Generovanie štítkov a podacích hárkov (Packeta / GLS / SPS / Slovenská pošta API).
- Tracking číslo na `Shipping` + odkaz v e-maile a v účte + notifikácia „zásielka je na ceste".
- Cena dopravy podľa hmotnosti a hodnoty — `weight` na variante už existuje, ale
  `ShippingMethod` ho nevyužíva. Doprava zadarmo od X € je najlacnejší nástroj na zvýšenie
  priemernej objednávky.

---

### 1.7 Recenzie a dôveryhodnosť

`ui/src/components/pages/nazoryZakaznikov.vue` je statický komponent. Neexistuje model
recenzie, hodnotenia ani overený nákup.

**Čo doplniť**

- Tabuľka `reviews` s väzbou na `order_product` (overený nákup), moderáciou a odpoveďou predajcu.
- `AggregateRating` + `Review` do JSON-LD produktu — hviezdičky vo výsledkoch Google majú
  merateľný vplyv na CTR.
- Automatický e-mail „ohodnoťte nákup" X dní po expedícii (napojiteľné na existujúci
  `OrderExpedition` notification flow).
- Voliteľne Heureka „Overené zákazníkmi" — v SK segmente to je silný signál dôvery.

---

### 1.8 Product Schema a feedy

`ui/src/models/seo.js` rieši Organization, WebSite a ItemList. Chýba **`Product` JSON-LD
s `offers`** (cena, mena, dostupnosť, dodacia lehota) na detaile produktu — to je najdôležitejšia
štruktúrovaná dáta pre e-shop.

Ďalej chýbajú výstupné feedy:

- **Google Merchant Center** XML/TSV feed → Google Shopping.
- **Heureka.sk** feed (a prípadne Najnakup / Pricemania).
- Feed sa dá generovať Laravel commandom do `storage` a servírovať cachovane —
  1 endpoint, veľký dopad na akvizíciu.

---

### 1.9 Konverzné mechaniky, ktoré úplne chýbajú

| Funkcia | Prečo | Náročnosť |
|---|---|---|
| Súvisiace / často kupované spolu | Vlajka + stožiar + držiak je prirodzený set | S |
| Naposledy prezerané | localStorage, žiadny backend | XS |
| Wishlist / uložiť na neskôr | B2B zákazník pripravuje rozpočet mesiace dopredu | S |
| Porovnanie produktov | Rozmery a materiály sa dajú porovnávať v tabuľke | M |
| Množstevné zľavy (pásma) | 10 ks / 50 ks / 100 ks — pre obce zásadné | M |
| „Stráž dostupnosť" e-mail | Zachytí dopyt na vypredaný tovar | S |
| Opustený košík e-mail | Košík je už v localStorage, stačí ho spárovať s e-mailom | M |
| Newsletter | Žiadny zber e-mailov ani segmentácia | S |
| Cookie consent + GA4/GTM | **Dnes nula analytiky** — viď 2.6 | S |

---

### 1.10 B2B režim — najväčšia príležitosť

E-shop cieli na obce, školy a firmy, ale správa sa ako B2C. Zároveň už má polovicu
infraštruktúry hotovú (IČO lookup cez `api.orsf.sk` v `CheckoutController`, `Customer`
s `ico`/`dic`/`ic_dic`, role, kupóny). Doplnenie B2B vrstvy je to, čím sa dá byť
**lepší ako štandard**, nie len na úrovni štandardu:

- **Cenové hladiny** na zákazníka/skupinu (`customer_price_groups` + prepočet vo `ProductResource`).
- **Ceny bez DPH ako primárne** pre prihlásených platiteľov DPH (dnes je bez DPH len
  doplnkový riadok v `PublicProductShow.vue`).
- **Platba na faktúru so splatnosťou 14/30 dní** a kreditným limitom — obec nezaplatí kartou.
- **Číslo objednávky odberateľa** (`customer_reference`) na objednávke aj faktúre —
  bez toho nemá obec ako spárovať doklad s rozpočtovou položkou.
- **Dopyt / cenová ponuka** namiesto priameho nákupu pri zákazkovej výrobe.
  `made_to_order` na produkte už existuje — chýba k nemu proces.
- **Hromadná objednávka podľa kódov** (vloženie zoznamu `kód;množstvo`).
- **Viac používateľov pod jedným zákazníkom** s rolami (objednávateľ vs. schvaľovateľ).
  `Customer` → `User` väzba už existuje, treba nad ňou postaviť oprávnenia.

### 1.11 Konfigurátor vlajky na mieru

Prirodzené rozšírenie `made_to_order`: rozmer na mieru s výpočtom ceny podľa m²,
výber materiálu a lemovania, **nahranie loga/erbu** a jednoduchý živý náhľad.
Výstup ide do objednávky ako príloha + poznámka pre výrobu. Toto v segmente nemá
takmer nikto a je to jasný dôvod, prečo si vybrať vás.

---

## 2. Technika a výkon

### 2.1 SPA bez SSR/prerenderu — najväčšia technická brzda

`ui/index.html` má poctivý statický základ a `ui/src/models/seo.js` prepisuje `<head>`
za behu. Googlebot to zvládne, ale:

- **Facebook, LinkedIn, Messenger, WhatsApp scrapery JS nespúšťajú** — pri zdieľaní
  ktoréhokoľvek produktu sa vždy zobrazí default `og:image` a default titulok.
- Seznam a menšie vyhľadávače JS rendrujú obmedzene.
- Heureka a porovnávače čítajú buď feed, alebo HTML.
- LCP je horší, lebo obsah čaká na stiahnutie a spustenie bundlu.

**Riešenia podľa náročnosti**

1. **Prerender pri builde** (`vite-plugin-prerender` / Puppeteer post-build) pre homepage,
   kategórie a top produkty. Najlacnejšie, rieši ~80 % problému. *(S–M)*
2. **Dynamic rendering** — Nginx pošle botom prerenderovanú verziu. *(M)*
3. **Migrácia na Nuxt 3 alebo Laravel + Inertia SSR.** Najlepší výsledok, najväčší zásah. *(XL)*

Odporúčanie: začať bodom 1, k bodu 3 sa vrátiť, až keď bude e-shop generovať tržby,
ktoré takú investíciu odôvodnia.

### 2.2 Nula cachovania

`grep "Cache::"` v `api/app` nevracia **ani jeden výskyt**. Zároveň `config/cache.php`
má default `database` — najpomalší driver, ktorý navyše zaťažuje tú istú DB ako katalóg.
Redis je v `.env` aj v configu už pripravený.

**Čo cachovať**

| Dáta | TTL | Poznámka |
|---|---|---|
| `/attribute-facets` | 1 h | Mení sa len pri zmene taxonómie |
| Strom kategórií | 1 h | Statické |
| `/shipping-methods`, `/payment-methods` | 1 h | Číselníky |
| Katalóg (prvé strany bez filtra) | 5–15 min | Tagované, invalidácia v `ProductObserver` |
| Detail produktu | 15 min | Invalidácia pri zmene variantu/skladu |

Prepnúť `CACHE_STORE=redis` a doplniť invalidáciu cez model observery
(dnes existujú len `OrderObserver` a `OrderProductObserver`).

### 2.3 Obrázky

`api/app/Services/Images.php` robí len dve veľkosti (1000 px a 280 px) v pôvodnom formáte.
Chýba **WebP/AVIF, `srcset`, `loading="lazy"`, `width`/`height` atribúty aj CDN**.
Na katalógu s 12+ obrázkami je to priamy zásah do LCP a CLS — teda do Core Web Vitals,
ktoré Google používa ako ranking signál a Merchant Center kontroluje.

Navyše: `folderPath()` používa `$this->model->user_id`, čo pri `Product` (ktorý user_id
nemá v migrácii) skončí v ceste s prázdnym segmentom — stojí za overenie.

**Čo doplniť:** generovanie 3–4 rozmerov + WebP variant pri uploade (queue job, nie synchrónne),
`<picture>` so `srcset` v `cart.vue` a `PublicProductShow.vue`, `loading="lazy"` mimo prvej
obrazovky, CDN alebo aspoň `Cache-Control: immutable` na `/storage`.

### 2.4 Databáza a dotazy

- `bySearchInput` s `LIKE '%x%'` cez `description` = full scan (viď 1.5).
- `priceFrom`/`priceTo` používajú `whereRaw('COALESCE(NULLIF(sale_price,0), price)')` —
  nepoužiteľné s indexom. Riešenie: perzistovaný `active_price` stĺpec s indexom,
  prepočítavaný v `ProductVariantService`.
- `image_id` na `products` aj `product_variants` je bez FK constraintu.
- `products.attributes` (`string(255)`) je mŕtvy pozostatok pred zavedením taxonómie —
  odstrániť migráciou, kým sa oň niekto neoprie.
- Chýba profilovanie N+1. `HomeController::index` eager-loaduje `variants`,
  `defaultVariant`, `images` — ale `ProductResource` môže siahať aj na
  `attributesTaxonomy`. Nasadiť `laravel-debugbar` alebo Telescope v dev a prejsť
  katalóg + detail.

### 2.5 Sklad a súbeh

Dva reálne prevádzkové problémy:

1. **Žiadna rezervácia zásob pri objednávke.** `quantity` na variante sa neznižuje pri
   vytvorení objednávky (v observeroch to nie je) — dvaja zákazníci môžu kúpiť poslednú
   vlajku. Pri malom objeme sa to rieši telefonátom, pri rastúcom nie.
2. **Žiadna idempotencia checkoutu.** `CheckoutController::store` nemá idempotency key —
   dvojklik na „Objednať" alebo retry pri pomalej sieti vytvorí dve objednávky.
   Riešenie: klient generuje UUID, server ho uloží a druhý pokus vráti pôvodnú objednávku.

### 2.6 Analytika a meranie — kritické pre prevádzku

Nikde nie je GA4, GTM, Meta Pixel ani cookie consent (`grep "gtag|analytics|consent"` v `ui/src`
vracia len text v ochrane osobných údajov).

Bez toho nevidíte odkiaľ chodia zákazníci, kde odchádzajú z checkoutu, ani čo sa oplatí
inzerovať — a nemôžete robiť remarketing.

**Čo doplniť:** cookie consent banner (Consent Mode v2 je pre EU inzerciu povinný),
GTM + GA4 s e-commerce eventmi `view_item`, `add_to_cart`, `begin_checkout`, `purchase`,
`purchase` server-side ako záloha proti adblockerom, a **Microsoft Clarity** — je zadarmo
a nahrávky sedení odhalia problémy checkoutu rýchlejšie než akákoľvek analýza.

### 2.7 Bezpečnosť

Čo je v poriadku: throttle na login/register/checkout/kupóny, sanitizácia HTML pred `v-html`
(`ui/src/models/html.js` — whitelist značiek, čistenie atribútov, `rel="noopener nofollow"`),
GDPR-uvedomelé rozlíšenie staff vs. verejnosť v `CheckoutController::isStaff()`,
`.env` nie je v gite. Toto je nadpriemer.

Čo doplniť:

- **Bezpečnostné hlavičky**: CSP, `X-Content-Type-Options`, `Referrer-Policy`,
  `Permissions-Policy`, HSTS. Dnes žiadne.
- **Globálny API throttle** — limity sú len na 5 vybraných routách.
- **2FA pre admin účty** a vynútená sila hesla.
- **Audit log** administrátorských zmien (kto zmenil cenu, kto stornoval objednávku).
  Pri viacerých ľuďoch v administrácii je to otázka času, kedy to budete potrebovať.
- Overiť expiráciu Sanctum tokenov a `SESSION_ENCRYPT` na produkcii.
- Overiť, že sa CORS neotvára širšie, než na `FRONTEND_URL`.

### 2.8 Testy, CI a prevádzková viditeľnosť

`api/tests` obsahuje **4 feature testy** (`CheckoutTest`, `CustomerServiceTest`,
`PublicProductResourceTest`, `ExampleTest`). Nekryté sú pritom najdrahšie cesty:
kupóny, výpočet ceny a DPH, storno, vratky, expedícia, oprávnenia rolí.

- Doplniť feature testy na uvedené cesty (cieľ: kritická cesta objednávky 100 %).
- **CI pipeline** (GitHub Actions): `pint --test`, `phpunit`, `npm run build`.
  `laravel/pint` je v dev závislostiach, ale nikde sa nespúšťa.
- Pridať **PHPStan/Larastan** na úroveň 5+.
- Frontend: chýba ESLint aj typová kontrola. Kód je mix `.js` a `.ts` (stores sú TS,
  komponenty prevažne JS) — buď to zjednotiť, alebo aspoň zapnúť `vue-tsc` na `.ts` súbory.
- **Sentry** (backend aj frontend) — dnes o chybe zákazníka neviete, kým nezavolá.
- **Zálohy**: `spatie/laravel-backup` s DB + `storage/app/public` mimo servera.
- Healthcheck endpoint + uptime monitoring.

### 2.9 Queue worker — overiť na produkcii

Všetky notifikácie implementujú `ShouldQueue` a `QUEUE_CONNECTION` je `database`.
Ak na produkcii nebeží `queue:work` pod Supervisorom, **neodošle sa ani jeden e-mail**
a objednávky sa tvária ako úspešné. Zároveň `config/mail.php` má default mailer `log`.
Overiť ako prvé — je to trieda chyby, ktorá sa objaví ticho.

### 2.10 Ostatné

- Žiadne verzovanie API (`/api/v1`) a žiadna OpenAPI dokumentácia.
- `decimal(8,2)` na cenách = strop 999 999,99. Pri hodnote objednávky (nie položky) to
  môže byť pri veľkých zákazkách tesné — skontrolovať typy súčtových stĺpcov na `orders`.
- Sitemap sa generuje pri builde z API. Ak API pri builde nebeží, vznikne sitemap
  bez produktov — zvážiť generovanie na strane Laravelu a servírovanie z API.

---

## 3. Admin a prevádzka

### 3.1 Chýbajúci prehľad podnikania

`OrderStatisticsService` a `OrderStatistics.vue` sú dobrý základ, ale dashboard neukazuje,
čo prevádzkar potrebuje ráno vidieť:

- Tržby dnes / týždeň / mesiac + porovnanie s minulým obdobím.
- **Marža** — vyžaduje nákupnú cenu na variante, ktorá dnes v schéme nie je.
- Top produkty a top zákazníci.
- Objednávky čakajúce na platbu / na expedíciu / v riešení.
- Sklad pod minimom (vyžaduje `min_quantity` na variante).
- Konverzný pomer (po nasadení analytiky).

### 3.2 Hromadné operácie

Administrácia je celá po jednej položke. Pri raste katalógu to prestane stačiť:

- **Import/export produktov a cien cez XLSX/CSV** — pre plošnú zmenu cien alebo
  aktualizáciu skladu od dodávateľa. `CustomerExportController` a `UserExportController`
  už export vedia, existuje teda vzor, na ktorom sa dá stavať.
- Hromadná zmena stavu objednávok a hromadná tlač štítkov/dodacích listov.
- Hromadné priradenie kategórií a vlastností.
- Duplikovanie produktu vrátane variantov.

### 3.3 Skladová evidencia

`Stock` dnes reprezentuje expedičnú položku, nie skladovú kartu. Chýba:

- Príjemky (v migrácii `add_receipt_fields_to_stocks_table` je začiatok — dotiahnuť).
- História pohybov (príjem / výdaj / oprava / inventúra) ako nemenný log.
- Nákupná cena a priemerná skladová cena → marža.
- Minimálna zásoba + automatické upozornenie.
- Inventúra s protokolom.

### 3.4 Napojenie na účtovníctvo

Nula integrácií. Na SK trhu je štandard export/synchronizácia do **Pohody**, **Omegy**,
**SuperFaktúry** alebo **iDokladu**. Aj obyčajný XML/ISDOC export faktúr ušetrí účtovníčke
niekoľko hodín mesačne a je to práca na 1–2 dni.

### 3.5 Obsah a CMS

Obchodné podmienky a ochrana osobných údajov sú **zadrôtované vo Vue komponentoch**
(`ui/src/components/pages/obchodnePodmienky.vue`, `ochranaOsobnychUdajov.vue`).
Každá zmena právneho textu = zásah do kódu a redeploy.

- Jednoduchý model `Page` (slug, titulok, HTML obsah, SEO) a editácia v administrácii.
- **Blog / poradňa** — v tomto segmente je to najlacnejší zdroj organickej návštevnosti:
  „aké sú rozmery štátnej vlajky SR", „ako správne vyvesiť smútočnú vlajku",
  „vlajková výzdoba obce", „ako sa vešia vlajka na stožiar". Články priamo prelinkované
  na produkty. Toto konkurencia väčšinou nerobí.
- FAQ na produktovej stránke + `FAQPage` JSON-LD.

### 3.6 Komunikácia so zákazníkom

Existujú `OrderCreated`, `OrderUpdated`, `OrderExpedition`, `OrderCancelled`,
`OrderReturnProcessed`, `CouponIssued`, `UserInvited`, `ResetPassword` — slušné pokrytie.
Chýba:

- Potvrdenie prijatia platby a pripomienka nezaplatenej objednávky.
- „Zásielka je na ceste" s tracking odkazom.
- Žiadosť o recenziu po dodaní.
- Prehľad odoslaných e-mailov v administrácii (dnes neviete overiť, či e-mail odišiel).
- Jednotná brandovaná šablóna s náhľadom.

---

## 4. Navrhovaný postup

### Fáza 0 — bez toho to nie je e-shop *(4–6 týždňov)*

1. Overiť queue worker a mailer na produkcii — *hodiny, ale blokuje všetko ostatné*
2. Online platba + stav `AwaitingPayment` + webhooky
3. Faktúra PDF + číselný rad + zálohová faktúra
4. Cookie consent + GA4/GTM s e-commerce eventmi + Clarity
5. Prerender kritických stránok (homepage, kategórie, produkty)
6. Packeta widget v košíku + tracking

### Fáza 1 — konverzia *(4–6 týždňov)*

7. Kategórie v storefronte + `/kategoria/{slug}` + breadcrumbs + sitemap
8. `Product` JSON-LD s `offers`
9. Vyhľadávanie s našepkávačom (Scout + Meilisearch)
10. Zákaznícky účet + opakovaná objednávka
11. Recenzie s overeným nákupom + `AggregateRating`
12. Súvisiace produkty, naposledy prezerané, doprava zadarmo od X €
13. Rezervácia zásob + idempotencia checkoutu

### Fáza 2 — B2B diferenciátor *(6–8 týždňov)*

14. Cenové hladiny + ceny bez DPH pre prihlásených platiteľov DPH
15. Platba na faktúru so splatnosťou + kreditný limit
16. Číslo objednávky odberateľa + hromadná objednávka podľa kódov
17. Dopyt / cenová ponuka pre `made_to_order`
18. **Konfigurátor vlajky na mieru s nahraním loga**
19. Množstevné zľavové pásma

### Fáza 3 — rast *(priebežne)*

20. Google Merchant + Heureka feedy
21. Opustený košík + newsletter
22. Blog / poradňa + CMS stránky
23. Import/export produktov, dashboard s maržou, skladová evidencia
24. Napojenie na účtovníctvo

### Priebežne (technický dlh)

- Redis cache + invalidácia cez observery
- WebP/AVIF + `srcset` + lazy loading + CDN
- Testy kritických ciest + CI (Pint, PHPUnit, PHPStan)
- Sentry, zálohy, healthcheck, bezpečnostné hlavičky, 2FA, audit log
- Vyčistenie schémy (`products.attributes`, FK na `image_id`, indexovateľný `active_price`)

---

## 5. Čo je už dnes nad štandardom

Aby bol obraz úplný — tieto veci netreba riešiť a stoja za zachovanie:

- Model variantov ako skladových položiek s cenou, skladom, EAN a váhou. Väčšina malých
  e-shopov to má nesprávne na úrovni produktu.
- Fasetový filter s korektnou logikou OR v rámci vlastnosti a AND medzi vlastnosťami.
- `OrderStatus` s čiastočnou expedíciou a rozlíšením uložených vs. vypočítaných stavov.
- Vratky ako samostatný proces (`OrderReturn` + `OrderReturnItem`) s vlastným workflow.
- Kupónový systém vrátane automatického vydávania po objednávke.
- GDPR-uvedomelé rozlíšenie staff vs. verejný prístup pri IČO lookupe.
- Sanitizácia HTML whitelistom pred `v-html`.
- SEO modul s allowlistom indexovateľných rout — bezpečnejší návrh než blacklist.
- Komentáre v kóde vysvetľujú *prečo*, nie *čo*. To je vzácne.
