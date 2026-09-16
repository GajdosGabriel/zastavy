# Analýza projektu zastavy-vlajky.sk

Dátum: 14. 9. 2026. Rozsah: Laravel API, Vue/Pinia frontend, objednávky, katalóg, sklad, oprávnenia, testy a návrhy rozvoja. Platobná brána je mimo rozsahu.

Analýza vychádza zo súčasného zdrojového kódu. Nálezy zo statickej kontroly nie sú tvrdením, že už nastal incident. Produkčné nastavenia, reálne dáta, návštevnosť, zálohy a správanie nasadeného webu neboli overované. Existujúci ANALYZA-ESHOP.md z augusta zostáva nezmenený; niektoré jeho tvrdenia už nezodpovedajú kódu, napríklad zákaznícky prístup už existuje.

## Celkové hodnotenie

Projekt má použiteľný základ pre B2B predaj a interné spracovanie zákaziek: varianty, vlastnosti a filtre, čiastočné expedície, vratky, kupóny, adresár doručenia, prílohy, kontrolu zákazníckych údajov a zákaznícke oprávnenia. Ceny objednávky sa načítavajú na serveri a čerpanie kupónu používa zámok v transakcii. Frontend načítava stránky postupne a zostavenie funguje.

Najväčšou slabinou je hranica medzi anonymným zákazníkom, firemným kontaktom a internou obsluhou. Ďalšou sú historické údaje objednávok a konzistencia pri súbežnom spracovaní. Odporúčam najprv opraviť tieto oblasti a až potom rozširovať predajné funkcie. Celkový prepis aplikácie z nálezov nevyplýva.

## Prioritné nálezy

### P0 — Verejný checkout môže meniť cudzie zákaznícke údaje

`CustomerService::findCustomer()` akceptuje ID z požiadavky, prípadne vyhľadá zákazníka podľa IČO. `handleCheckout()` potom aktualizuje jeho firemné aj kontaktné údaje. Verejný checkout túto službu volá bez kontroly vlastníctva; `OrderRequest` navyše nezakazuje `customer.id` a služba dostáva pôvodný vstup, nie striktne povolené polia.

**Dopad:** anonymná objednávka môže prepísať existujúcu firmu, adresu a e-mail. Poznanie IČO nie je overením oprávnenia konať za firmu. Následné správy pre staršie objednávky môžu používať zmenený firemný kontakt.

**Návrh:** verejný checkout ukladá údaje ako odtlačok konkrétnej objednávky; nesmie priamo aktualizovať existujúci firemný profil. Interný výber zákazníka a zmeny profilu oddeliť, autorizovať a auditovať. Vstupné ID vo verejnom toku odmietnuť. Pridanie ďalšej kontaktnej osoby nesmie automaticky znamenať prístup k celej firme.

**Kód:** [CustomerService.php](api/app/Services/CustomerService.php), [StoreCheckout.php](api/app/Actions/StoreCheckout.php), [OrderRequest.php](api/app/Http/Requests/OrderRequest.php).

### P1 — Zákaznícke oprávnenie upraviť objednávku otvára interné operácie

`OrderPolicy::portalCan()` štandardne povoľuje firemnému kontaktu `orders.update`. Rovnaké oprávnenie používa endpoint expedície a spracovania vratiek. `OrderController::update()` povoľuje cez všeobecnú úpravu aj `status` a `isOpened`; požiadavka bez `customer` tieto polia nevaliduje.

**Dopad:** zákazník s prístupom k vlastnej firemnej objednávke môže volať operácie určené skladu alebo meniť interný stav. Skrytie tlačidla vo frontende to neochráni.

**Návrh:** samostatné oprávnenia na expedíciu, spracovanie vratky, zmenu stavu a zákaznícku úpravu. Povoliť konkrétne prechody stavov a polia podľa aktéra. Overiť aj zamýšľaný rozsah interných rolí: `ownsOrder()` dáva všeobecný prístup iba super-adminovi.

**Kód:** [OrderPolicy.php](api/app/Policies/OrderPolicy.php), [OrderController.php](api/app/Http/Controllers/Api/Dashboard/OrderController.php), [OrderShippingController.php](api/app/Http/Controllers/Api/Dashboard/OrderShippingController.php), [OrderReturnController.php](api/app/Http/Controllers/Api/Dashboard/OrderReturnController.php).

### P1 — Blokovanie účtu nezastaví už vydaný token

Pri prihlásení sa kontroluje `active` a blokovaný stav. Middleware pri ďalších požiadavkách kontroluje roly alebo priradenie k firme, nie aktívnosť. Aktualizácia používateľa pri blokovaní neruší tokeny. Sanctum má `expiration = null`.

**Dopad:** predtým prihlásený používateľ môže pokračovať s existujúcim tokenom aj po zablokovaní.

**Návrh:** aktívnosť overovať pri každom chránenom prístupe a pri blokovaní zrušiť tokeny. Pridať regresný test s tokenom vydaným pred zablokovaním. Pre administráciu doplniť správu relácií a neskôr druhý faktor.

**Kód:** [SanctumController.php](api/app/Http/Controllers/Api/SanctumController.php), [DashboardMiddleware.php](api/app/Http/Middleware/DashboardMiddleware.php), [AdminMiddleware.php](api/app/Http/Middleware/AdminMiddleware.php), [UserController.php](api/app/Http/Controllers/Api/SuperAdmin/UserController.php), [sanctum.php](api/config/sanctum.php).

### P1 — Nezverejnený produkt nie je skutočne neverejný

Verejné detaily `/api/products/{product}` a `/api/homes/{...}` nemajú kontrolu publikovania produktu. Resource vracia aj načítané nezverejnené varianty vrátane cien a množstiev. Pri route `homes` je navyše pomenovanie parametra odlišné od argumentu `$product`; tento duplicitný detail treba otestovať alebo odstrániť.

**Dopad:** jednoznačne dostupný endpoint `products/{product}` môže sprístupniť rozpracovaný alebo stiahnutý sortiment podľa ID. Filtrovanie zoznamu a zákaz objednať nepublikovaný produkt nestačia.

**Návrh:** verejný detail vracia 404 pri nepublikovanom produkte a iba publikované varianty. Interný náhľad má samostatnú autorizáciu. Oddeliť verejný resource od administratívneho.

**Kód:** [ProductController.php](api/app/Http/Controllers/Api/SuperAdmin/ProductController.php), [HomeController.php](api/app/Http/Controllers/Api/HomeController.php), [ProductResource.php](api/app/Http/Resources/ProductResource.php).

### P1 — Staršie objednávky menia obsah spolu so zákazníkom a produktom

Kontaktné polia objednávky už existujú, ale verejný detail používa kontakt z aktuálneho zákazníka. Fakturačné údaje a bežná doručovacia adresa sa takisto čítajú zo zákazníka. Názov položky sa číta zo súčasného produktu; uložený je názov variantu, cena a počet.

**Dopad:** zmena firmy, sídla alebo názvu produktu zmení zobrazenie už vybavenej objednávky. Vlastná doručovacia adresa má odtlačok, bežné doručenie na sídlo ho nemá. Expedícia posiela správu na aktuálny zákaznícky e-mail, čo pri viacerých kontaktoch nemusí byť objednávateľ.

**Návrh:** vždy uložiť fakturačné a doručovacie údaje, objednávateľa a identitu položiek pri objednaní. Neskoršie zmeny robiť vedome s históriou. Správy smerovať na kontakt objednávky. Pri historickom doplnení dát označiť, že ide o rekonštrukciu, nie garantovaný pôvodný stav.

**Kód:** [Order.php](api/app/Models/Order.php), [PublicOrderController.php](api/app/Http/Controllers/Api/PublicOrderController.php), [DeliveryAddressService.php](api/app/Services/Delivery/DeliveryAddressService.php), [StoreOrder.php](api/app/Actions/StoreOrder.php).

### P1 — Súbeh môže poškodiť sklad a zdvojiť expedíciu

`StockObserver::apply()` načíta množstvo a uloží vypočítanú hodnotu. Dva procesy môžu vychádzať z rovnakého stavu a prepísať si výsledok. Expedícia síce používa transakciu, ale neuzamyká objednávku pred načítaním zostávajúcich množstiev.

**Dopad:** dva súbežné skladové pohyby môžu stratiť časť zmeny; dve expedície môžu expedovať ten istý zostatok. Ide o riziko odvodené z kódu, nie o vykonaný záťažový test.

**Návrh:** atómová aktualizácia skladového množstva a jednotné uzamykanie objednávky pri expedícii, storne aj vratkách. Opakovaná požiadavka na rovnakú operáciu má vrátiť pôvodný výsledok. Doplniť integračné testy súbehu na MySQL.

**Kód:** [StockObserver.php](api/app/Observers/StockObserver.php), [ShippingService.php](api/app/Services/ShippingService.php), [OrderShippingController.php](api/app/Http/Controllers/Api/Dashboard/OrderShippingController.php).

### P1 — Checkout nekontroluje dostatok skladu ani úplné podmienky objednania

`resolveItems()` overuje publikovanie a minimálny odber, ale nie objednané množstvo voči zásobe. Server potichu zvýši množstvo na minimum. Doprava je nullable a overuje iba existenciu ID; bez dopravy vzniká nulový poplatok. `variant_id` nemá vlastné validačné pravidlá ani väzbu na poslané ID produktu. Chýbajúci `customer` prepne celý request na pravidlá pre úpravu poznámky.

**Návrh:** oddeliť vytvorenie objednávky od jej úpravy. Na serveri vynucovať aktívnu a použiteľnú dopravu, vzťah variantu k produktu a povinné údaje. Jasne odlíšiť skladový tovar, nesledovaný sklad a výrobu na zákazku. Pri skladovom tovare rezervovať zásobu alebo výslovne podporiť objednanie nad zásobu. Zmenu ceny či minimálneho počtu ukázať zákazníkovi pred potvrdením.

**Kód:** [OrderRequest.php](api/app/Http/Requests/OrderRequest.php), [StoreOrder.php](api/app/Actions/StoreOrder.php), [StoreCheckouts.ts](ui/src/store/StoreCheckouts.ts).

### P1/P2 — Opakované odoslanie a číslovanie objednávok

Checkout nemá identifikátor opakovanej požiadavky. Pri strate odpovede a opakovaní môže založiť ďalšiu objednávku. Číslo objednávky vzniká počítaním riadkov v mesiaci; pri súbežných transakciách sa nemusia navzájom vidieť. `serial_number` nemá unikátny index.

**Návrh:** jedinečný kľúč odoslania košíka a databázová ochrana duplicity. Pre číslovanie použiť uzamknutý číselný rad a unikátny index, až po preverení existujúcich duplicít.

**Kód:** [CheckoutController.php](api/app/Http/Controllers/Api/CheckoutController.php), [StoreOrder.php](api/app/Actions/StoreOrder.php), [migrácia objednávok](api/database/migrations/2025_04_02_024505_create_orders_table.php).

## Technický dlh a prevádzka

**Ďalšia chyba skladu pri vratke:** `OrderReturnController::process()` vytvára pohyb so záporným množstvom a `shipping_id = null`. `StockObserver::delta()` takýto pohyb vyhodnotí ako záporný príjem a zníži množstvo variantu. Spracovaná vratka tak znižuje expedované množstvo objednávky, ale súčasne znižuje aj fyzický sklad. Tieto dve veličiny treba oddeliť: vrátený predajný kus má sklad zvýšiť, poškodený kus má ísť do samostatného režimu. Explicitný typ pohybu je bezpečnejší než odvodzovanie významu len zo znamienka a `shipping_id`. Priorita P1; doplniť test skladu po vratke, ktorý v aktuálnej sade chýba.

| Oblasť | Zistenie a návrh |
|---|---|
| Testy a CI | Existujú užitočné funkčné testy; frontend nemá testovací ani typecheck skript a v repozitári som nenašiel CI workflow. Zaviesť opakovateľný MySQL testovací job, build, kontrolu typov a kritické zákaznícke scenáre. |
| Zastaraný test | `CustomerServiceTest` stále očakáva zdieľanie používateľa podľa e-mailu medzi firmami, hoci súčasná služba tomu zámerne bráni. Používa tiež odstránené zákaznícke pole `name`. Aktualizovať očakávania podľa schváleného dátového modelu. |
| Identita a kontakty | `users` plní rolu prihlasovacieho účtu aj firemného kontaktu. Login vyberá najstarší záznam podľa e-mailu. Zaviesť samostatnú identitu a členstvá vo firmách, ak má jedna osoba bezpečne pracovať pre viac organizácií. |
| API kontrakty | Verejný a interný tok zdieľajú requesty a resources. Rozdeliť ich; služby majú dostávať validované údaje. Obmedziť plošné `guarded = []` a používanie pôvodného requestu. |
| Doménová logika | Niektoré Actions vykonávajú zápisy už v konštruktore. Presunúť do explicitných metód. Stavové prechody, výpočet súm a rezervácie sú vhodné samostatné služby. |
| Peniaze | DB používa decimal, výpočty však prechádzajú cez float. Zjednotiť presnú aritmetiku a pravidlá zaokrúhlenia pre položky, zľavy a sumár. |
| Výkon katalógu | `HomeController::index()` nenačítava kategórie, ale `ProductResource` ich číta pri každom produkte. Varianty môžu doťahovať obrázky. Doplniť eager loading podľa resource a sledovať počet SQL dotazov na stránku. |
| Frontend požiadavky | Globálny boolean načítavania nezvláda viac súbežných requestov. Filtre nemajú v `StoreHome` ochranu proti prepísaniu výsledku staršou odpoveďou. Použiť stav na konkrétnu operáciu a rušenie alebo číslovanie requestov. |
| Notifikácie | Vytvorenie objednávky plánuje správy v transakcii; queue konfigurácia má `after_commit = false`. Pri Redis/SQS alebo inom spojení môže worker predbehnúť commit. Doručovanie explicitne viazať na commit; pri rovnakej databázovej queue je riziko odlišné. |
| Súbory | Zlyhanie checkoutu môže nechať osamotený upload, čo priznáva komentár v `StoreOrder`. Doplniť dočasný stav uploadov a čistenie nepotvrdených súborov. |
| Verejné odkazy | UUID objednávky umožňuje čítať detail a prílohy bez expirácie. Zvážiť odvolateľný prístupový token, obmedzenie údajov a evidenciu prístupov. Samotné UUID nie je ľahko uhádnuteľné; problémom je životnosť uniknutého odkazu. |
| Prevádzková pripravenosť | README sú šablóny. Zdokumentovať nasadenie, queue worker, scheduler, rollback, obnovu záloh a alarmy. Existenciu produkčných monitorov či záloh nemožno z repozitára potvrdiť. |
| Databázový export v Gite | `1781329857-scz0kqs2.sql.gz` je sledovaný Gitom. Obsah som neotváral. Overiť, či je anonymizovaný; reálne dáta patria do riadených záloh, nie do bežnej histórie zdrojov. |

SEO už má titulky, canonical, JSON-LD a sitemap; netreba ich navrhovať od nuly. Obsah a metadáta však vznikajú v prehliadači a sitemap sa aktualizuje pri builde. Ďalší krok je overenie indexácie a náhľadov zdieľania na nasadenom webe, samostatné kategórie a regenerácia sitemap pri zmene sortimentu. Až podľa výsledkov zvažovať prerender verejných stránok. Administrácia ho nepotrebuje.

## Návrhy nových funkcií

Nasledujúce poradie je produktový návrh podľa charakteru kódu, nie výsledok analýzy tržieb alebo používateľského výskumu.

| Priorita | Funkcia | Prínos a prvá použiteľná verzia |
|---|---|---|
| Vysoká | Zopakovať objednávku | Z histórie vložiť dostupné varianty do košíka, prepočítať aktuálne ceny a označiť zmenené alebo vyradené položky. Vhodné pre pravidelné nákupy obcí, škôl a firiem. |
| Vysoká | Cenová ponuka → objednávka | Dopyt s množstvom, rozmermi a prílohami; interné nacenenie, verzia a platnosť ponuky; prijatie zákazníkom vytvorí objednávku. |
| Vysoká | Schvaľovanie grafiky | Verzie návrhu vlajky, pripomienky, explicitné schválenie zákazníkom s časom a identitou. Výroba sa viaže na schválenú verziu. |
| Vysoká | Termín a plán výroby | Pri zákazkovom tovare termín dodania a interný prehľad zákaziek podľa termínu, materiálu a stavu. Upozornenie na meškanie. |
| Vysoká | Lepšia orientácia v katalógu | Viditeľné hľadanie názvu/kódu, kategórie podľa použitia a zdieľateľné filtre v URL. Pri preverení homepage chýba priamo v nej ovládanie stránkovania, hoci API stránkuje; doplniť ho a overiť dostupnosť celého sortimentu. |
| Stredná | Doklady pri objednávke | Automaticky pripojiť doklad z účtovného systému, prípadne začať kontrolovaným importom. Samostatný model fakturácie som v kóde nenašiel; existujúce prílohy môžu slúžiť ako základ. |
| Stredná | Expedícia a sledovanie zásielky | Evidencia dopravcu, čísla zásielky a odkazu; neskôr tlač štítku. Najprv bezpečne oddeliť interné expedičné oprávnenia. |
| Stredná | Minimálne zásoby a dopĺňanie | Prah na variante, prehľad dostupného a rezervovaného množstva, návrh doplnenia a periodická kontrola skladu. |
| Stredná | Firemný portál | Viac kontaktov s konkrétnymi právami, výber organizácie, interné číslo objednávky, nákupné zoznamy a prípadné schvaľovanie nákupov. Nadväzuje na existujúci portál. |
| Stredná | Množstevné a firemné ceny | Cenové pásma variantu a cenník firmy s platnosťou. Výpočet musí zostať na serveri a cena ponuky/objednávky musí mať odtlačok. |
| Neskôr | Meranie predajného procesu | Udalosti produkt → košík → odoslanie, chyby formulára a hľadania bez výsledkov. Rozvoj potom prioritizovať podľa merateľných prekážok. |

Prvé produktové investície by som smeroval do opakovaných objednávok, ponúk a schvaľovania grafiky. Najlepšie využívajú existujúci B2B a zákazkový charakter systému. Vernostný program alebo rozsiahly konfigurátor by som odložil, kým sa nepotvrdí konkrétny dopyt.

## Odporúčané poradie realizácie

1. **HOTOVO — Bezpečné hranice (14. 9. 2026):** verejný checkout a zákaznícke dáta, expedičné oprávnenia, blokovanie tokenov a neverejný sortiment. Implementácia aj regresné API testy pre anonymného zákazníka, firemný kontakt a jednotlivé interné roly sú dokončené. Overenie: 85 testov, 347 assertions a frontend build prešli. Zmeny sú v projekte, zatiaľ bez nasadenia.
2. **HOTOVO — Spoľahlivá objednávka (15. 9. 2026):** historické odtlačky, správni príjemcovia správ, validácia dopravy/variantov, ochrana opakovaného odoslania a číselný rad.
3. **HOTOVO — Spoľahlivý sklad (15. 9. 2026):** atómové pohyby, spoločné zámky objednávok, kontrola zásoby pri expedícii, opravené vratky a súbežné testy na MySQL. Podľa zvoleného obchodného pravidla sú objednávky nad sklad povolené a rezervácie sa nevytvárajú.
4. **HOTOVO — implementácia udržateľnosti (16. 9. 2026):** CI, typová kontrola, ochrana testovacej databázy, stabilný počet SQL dotazov katalógu, prevádzkové kontroly a dokumentácia. Aktivácia monitora a doručovanie alarmov na hostingu zostávajú krokom nasadenia.
5. **Rozvoj predaja:** dokončenie navigácie katalógu, opakovanie objednávok, ponuky, schvaľovanie grafiky a termíny výroby.

Presný časový odhad závisí najmä od toho, či už treba zachovať produkčnú históriu a aké majú byť oprávnenia firemných kontaktov. Bez týchto rozhodnutí by odhad v dňoch pôsobil presnejšie, než umožňujú podklady.

## Vykonané overenie

- Frontend: priamy produkčný Vite build prešiel, 272 modulov, približne 8,3 sekundy. Výstup bol v dočasnom adresári. Sieťový krok generovania sitemap sa nespúšťal; nejde teda o overenie celého `npm run build` vrátane produkčného API.
- Backend: 60 existujúcich testov na izolovanej SQLite `:memory:` databáze, 58 prešlo, 1 error a 1 failure; 218 assertions. Vývojová ani produkčná databáza sa nepoužila.
- `CustomerServiceTest` skončil na chýbajúcej tabuľke: používa `DatabaseTransactions` a sám nepripravuje schému izolovanej databázy.
- Test filtra stavu objednávok narazil na chýbajúcu SQLite funkciu `greatest`. Projekt predpisuje MySQL, takže tento výsledok nepreukazuje chybu MySQL produkcie. Plná MySQL sada nebola spustená.
- Nebol vykonaný browserový audit vzhľadu, prístupnosti, reálnej indexácie ani záťažový test. Bezpečnostné a súbehové nálezy vychádzajú z konkrétnych ciest v kóde.
- Aplikačný kód sa nemenil. Výstupom je tento dokument.

## HOTOVO — realizácia bodu 1: bezpečné hranice (14. 9. 2026)

Bod 1 je implementovaný v pracovnom strome projektu:

- Verejné vytvorenie objednávky odmieta ID existujúceho zákazníka a uloženú adresu podľa ID. Samotné IČO už neaktualizuje existujúcu firmu ani nepripája nový kontakt k jej histórii. Rovnaké pravidlo platí aj pre vytvorenie objednávky cez zákaznícky dashboard.
- Verejný tok vytvára samostatný zákaznícky záznam s odoslanými údajmi. Pri prihlásení zostane objednávka priradená prihlasovacej identite. Toto môže zvýšiť počet zákazníckych duplicít; automatické spájanie podľa IČO je zámerne vypnuté. Overené priraďovanie a úplné historické odtlačky patria do ďalšieho rozvoja.
- Interná obsluha môže vybrať existujúcu firmu; prepísať jej profil môže iba s oprávnením customers.update. Ak toto oprávnenie nemá, profil aj kontakty zostanú nezmenené.
- Všeobecná úprava objednávky je interná operácia. Zákazníkovi zostáva prezeranie, vytvorenie a povolené storno; zmena doručenia cez existujúci tokenový odkaz zostáva zachovaná.
- Expedícia a správa vratiek vyžadujú aktívnu internú rolu, shippings.manage a prístup ku konkrétnej objednávke. Úprava položiek vyžaduje orderProducts.manage. Zachovaný je doterajší rozsah vlastníctva objednávok; všeobecný prístup má super-admin.
- Úprava a mazanie položky overujú, že položka patrí k objednávke v URL.
- Blokovanie, deaktivácia, zrušenie alebo archivácia účtu rušia tokeny cez observer. API navyše pri každej požiadavke kontroluje aktívnosť účtu, vrátane voliteľného prihlásenia pri verejnom katalógu.
- Nezverejnené produkty vracajú verejnosti 404 na oboch detailových endpointoch; nepublikované varianty ani nepublikovaný predvolený variant sa nevydávajú. Interný náhľad produktu vyžaduje oprávnenie na prezeranie.
- Frontend používa príslušné oprávnenia pre expedičné a vratkové ovládanie a verejný košík neposiela interné ID prevzaté zo starého uloženého formulára.

Overenie: 85 backendových testov / 347 assertions prešlo na izolovanej SQLite databáze v pamäti; 25 nových scenárov pokrýva bezpečnostné hranice. Testovací harness dopĺňa SQLite ekvivalent MySQL funkcie greatest. Opravený bol zastaraný test zákazníckej služby a testy výberu uložených adries teraz používajú autorizovanú obsluhu. Frontend Vite build a PHP syntax prešli. Lokálnu MySQL testovaciu databázu nebolo možné vytvoriť, preto beh na MySQL nie je overený. Produkčná ani vývojová databáza sa nemenila; nasadenie sa nevykonalo. Zmena nevyžaduje novú migráciu.


## HOTOVO — realizácia bodu 2 (15. 9. 2026)

- Nové objednávky uchovávajú odtlačky fakturačných a kontaktných údajov, produktov, variantov a názvov dopravy/platby. API a e-maily používajú uloženú históriu. Doručenie na fakturačnú adresu vychádza z pôvodnej adresy objednávky.
- Zákaznícke oznámenia smerujú na kontakt objednávky, vrátane úprav, storna, expedície, vratky, adresy a kupónu. Fronta čaká na commit transakcie.
- Samostatná validácia vytvorenia vyžaduje aktívnu dopravu a overuje väzbu variantu k produktu. Množstvo pod minimom vracia chybu namiesto tichého zvýšenia. Úprava dopravy aktualizuje poplatok aj uložený názov.
- Verejný aj interný formulár používajú UUID kľúč odoslania. Rovnaký obsah a kľúč vrátia pôvodnú objednávku bez ďalších príloh či správ. Zmenený obsah alebo používateľ s rovnakým kľúčom dostane 409. Frontend uchováva kľúč pre opakovanie po strate odpovede a blokuje súbežné kliknutia.
- Mesačný číselný rad používa databázový zámok a unikátny index. Inicializácia zohľadňuje aj odstránené objednávky. Po migrácii je hromadné prečíslovanie zakázané; náhľad zostáva dostupný.
- Príkaz `orders:freeze-history` doplní chýbajúce odtlačky z dostupných údajov a označí objednávky ako `reconstructed`. Existujúce odtlačky neprepisuje. Nedokáže obnoviť pôvodné údaje zmenené pred implementáciou.

Overenie: 106 backendových testov / 454 assertions prešlo na izolovanej SQLite databáze, vrátane 21 nových scenárov spoľahlivosti. Prešli 4 frontendové testy a priamy produkčný Vite build do dočasného adresára. Generovanie sitemap, prehliadačový end-to-end test ani súbehový test na MySQL neboli vykonané. Zmeny sú v projekte; nasadenie ani úpravy vývojovej či produkčnej databázy sa nevykonali. Platobná brána nebola predmetom zmien.

### Nasadenie bodu 2

Backend a frontend nasaďte spoločne v údržbovom okne: vytváracie API teraz vyžaduje `idempotency_key` a `shipping_method_id`. Pred migráciou zabezpečte zálohu databázy. Migrácia `2026_09_15_100000_add_order_reliability` pred zmenou schémy kontroluje duplicitné čísla vrátane odstránených objednávok; pri duplicite sa zastaví. Duplicity treba individuálne preveriť a opraviť, nie automaticky prečíslovať celú históriu.

V adresári `api` vykonajte:

```sh
php artisan migrate
php artisan orders:freeze-history
php artisan orders:freeze-history --apply
php artisan queue:restart
```

Prvý beh `freeze-history` iba vypíše počet objednávok bez odtlačku. Až `--apply` uloží rekonštruované údaje. Vykonajte ho pred obnovením úprav profilov a katalógu. Pred produkciou overte migráciu a súbežné vytváranie aj na testovacej MySQL databáze. Rollback migrácie odstráni odtlačky a evidenciu odoslaní; po obnovení objednávok vyžaduje plán obnovy dát.


## Realizácia bodu 3 — spoľahlivý sklad (15. 9. 2026)

### Dohodnuté obchodné pravidlo

Objednávka môže presiahnuť zásobu a nevytvára rezerváciu. Bežný tovar so sledovaným skladom sa expeduje iba do aktuálne dostupného množstva. Kontrola beží v transakcii pod zámkom variantu; viac riadkov s tou istou variantou čerpá jednu spoločnú zásobu. Nedostatok vracia 422 a vráti späť celú expedíciu. Obsluha môže poslať menšiu čiastočnú expedíciu. Zákazkový tovar (`made_to_order`) a variant s `quantity = null` sa expedujú bez tejto podmienky. Nesledovaná zásoba zostáva `null`.

Verejný detail umožňuje objednať aj variant bez zásoby a označuje ho „na objednávku“ s potvrdením termínu dodania. Informácia o skutočnej skladovej dostupnosti zostáva samostatná.

### Implementácia

- Pohyb skladu používa atómový SQL prírastok. Pri príjme sa variant uzamkne ešte pred vložením pohybu, aby súbeh nevytvoril deadlock pri zámkoch cudzieho kľúča. Skladové API zapisuje pohyb aj zásobu v jednej transakcii.
- Expedícia, storno, úprava a zmazanie položiek, zmazanie objednávky a zmeny vratiek používajú spoločný zámok objednávky. Varianty sa pri viacpoložkovej expedícii a vratke zamykajú podľa ID. Služba expedície znovu načíta aktuálne položky a skladové pohyby.
- Expedícia podporuje `idempotency_key`; rovnaký kľúč a obsah vráti pôvodnú expedíciu, iný obsah s tým istým kľúčom dostane 409. Frontend blokuje súbežné kliknutia a pri chybe uchováva kľúč v stave otvorenej aplikácie. Starší klient môže kľúč vynechať; potom je chránený celkový zostatok objednávky, ale opakovaný čiastočný odber sa považuje za ďalšiu operáciu.
- Nová vratka oddeľuje zníženie expedovaného množstva od fyzického účinku na sklad (`inventory_delta`). Predajný vrátený kus zásobu zvýši; poškodený ju automaticky nezvýši. Obsluha zvolí zaradenie do dostupného skladu v potvrdení. Rozhodnutie sa uloží a zobrazí na vratke. Poškodený tovar nemá samostatnú skladovú lokáciu; jeho vyradenie z dostupnej zásoby je evidované vratkou.
- Spracovanie vratky opätovne overuje aktuálne expedované množstvo. Prekrývajúce sa čakajúce vratky nemôžu vrátiť viac kusov, než bolo expedovaných. Opakovanie spracovanej vratky nepridá ďalší pohyb ani ďalšiu notifikáciu; zmena uloženého rozhodnutia o zaradení do skladu vracia 409.
- Expedovanú položku nemožno znížiť pod expedované a stornované množstvo. Položky a objednávky so skladovou históriou nemožno zmazať. Pohyby objednávok nemožno ručne upravovať alebo mazať cez všeobecné skladové API.
- Prehľad pohybov rozlišuje vratku a zobrazuje skutočný fyzický účinok vrátane nuly. Súhrn započítava predajné vratky do príjmov.

### Nasadenie a historické údaje

Nasadiť backend a frontend spolu; pred spustením nového backendu vykonať v `api`:

```sh
php artisan migrate
php artisan stocks:audit-returns
php artisan queue:restart
```

Migrácia `2026_09_15_120000_add_stock_inventory_delta` pridá fyzický účinok vratky, rozhodnutie o zaradení do skladu a jedinečný kľúč expedície. Zásobu variantu rozšíri na podpísané BIGINT, aby zachovala existujúce nezáporné údaje aj umožnila záporný stav zákazkovej výroby a inventúrnych odpisov. Bežné expedície záporný stav nevytvárajú.

Historické vratky a fyzické zásoby sa automaticky neopravujú. `stocks:audit-returns` iba vypíše staré aktívne vratkové pohyby bez explicitného fyzického účinku. Ich pôvodné záporné účinky zostávajú zachované. Pred nasadením kontroly expedície porovnajte dotknuté varianty so skutočnou inventúrou a už vykonanými opravami; potrebné rozdiely zaevidujte inventúrnym pohybom. Automatické pripočítanie by mohlo zdvojiť skoršiu ručnú opravu.

Rollback odstráni nové údaje a ochranu kľúčov. Po vzniku nových vratiek vyžaduje plán obnovy dát; odstránenie `inventory_delta` by stratilo význam fyzických účinkov. Pri záporných zásobách sa rollback migrácie zastaví.

### Overenie

Samostatná lokálna databáza `zastavy_stock_test_20260915` bola vytvorená iba na testy. Súbehové testy spúšťajú dva samostatné PHP procesy za spoločnou štartovacou bariérou; overujú príjmy, opakovanú čiastočnú expedíciu, dve objednávky čerpajúce rovnaký sklad, expedíciu so stornom a dvojité spracovanie vratky. Test sa na SQLite preskočí; databáza musí mať v názve `_test` a test obnovuje jej schému.

Vývojová ani produkčná databáza sa nemenila. Nasadenie sa nevykonalo. Platobná brána zostala mimo rozsahu.

Výsledok overenia bodu 3: **124 backendových testov / 551 assertions na MySQL prešlo**, vrátane 18 nových regresných a súbehových scenárov. Prešli 4 existujúce frontendové testy a priamy produkčný Vite build (273 modulov). Sieťové generovanie sitemap a prehliadačový end-to-end test neboli spustené.


## HOTOVO — realizácia bodu 4 (16. 9. 2026)

- `.github/workflows/ci.yml` spúšťa backendové testy vrátane súbehu na izolovanom MySQL 8.4, kontrolu PHP syntaxe a Composer manifestu. Frontendový job vykoná `npm ci`, `vue-tsc`, testy a build bez volania produkčného API. Workflow beží pri pushi, PR a manuálnom spustení; má časové limity a iba právo čítať repozitár.
- TypeScript je zapnutý v strict režime pre existujúci TypeScript a komponenty s `lang="ts"`. Opravené sú typy PSČ/IČO/DIČ, parametrov routingu, oprávnení a formulárového množstva. Legacy JavaScript zostáva `checkJs=false`; nejde o plošnú migráciu všetkých komponentov.
- Starý `CustomerServiceTest` už bol opravený v bode 1; aktuálna sada prechádza. Spoločný testovací základ teraz pred `RefreshDatabase` odmietne produkčné prostredie alebo DB bez samostatného segmentu `test` v názve. Povolený SQLite beh je iba `:memory:`. Práva testovacieho DB účtu musia byť naďalej obmedzené na testovaciu DB.
- Katalóg hromadne načítava kategórie a obrázky variantov aj predvolených variantov. Regresný test porovnáva počet SQL dotazov pri jednom a šiestich produktoch; počet zostáva rovnaký a neprekročí 10. Doplnené eager loading je aj v administratívnom zozname a oboch detailoch.
- Globálne načítavanie počíta súbežné HTTP transporty. Katalóg prijíma iba najnovšiu odpoveď pre zoznam/detail; oneskorený výsledok ani chyba starého filtra neprepíšu nový stav. Štyri nové frontendové testy overujú prekrývajúce sa požiadavky, chybu, zrušenie a poradie odpovedí.
- Pridaná migrácia `2026_09_16_100000_create_failed_jobs_table`: projekt mal nakonfigurované databázové ukladanie zlyhaných úloh, ale bez zodpovedajúcej tabuľky. Nová tabuľka má aj index času zlyhania.
- `ops:check --json` kontroluje DB, cache, heartbeat plánovača a workera, backlog a nedávne zlyhané úlohy. Vracia exit kód 0/1 a pri probléme loguje `operations.unhealthy`. `OPS_MONITOR_ENABLED=true` zapne päťminútové heartbeat signály a lokálnu kontrolu. Predvolene je monitor vypnutý, kým sa nenasadí scheduler a worker so spoločnou trvalou cache. Testy overujú aj reálne spracovanie heartbeat úlohy databázovým workerom.
- Nahradené šablónové README a doplnené `docs/OPERATIONS.md`: nasadenie, Supervisor worker, cron, prahy monitorovania, reakcie na alarm, zálohy, skúška obnovy a rollback. Externý monitor musí zachytiť výpadok schedulera/celého servera a doručiť upozornenie. Doručovanie alarmov sa v tomto kroku nenastavovalo.
- Nové SQL exporty a `dist` sú ignorované Gitom; už sledovaný historický SQL export nebol otvorený, vymazaný ani odstránený z histórie.

### Overenie bodu 4

- **131 backendových testov / 572 assertions prešlo** na novej izolovanej DB `zastavy_maintenance_test_20260916`, PHP 8.3.31, lokálny server **MariaDB 10.4.32 cez MySQL ovládač**. Tento beh zahŕňa päť viacprocesových testov skladu. Skoršie označenie lokálneho servera ako MySQL znamenalo MySQL pripojenie; konkrétna verzia servera bola overená teraz.
- **8 frontendových testov**, `npm run typecheck` a `npm run build:ci` prešli; build má 275 modulov. Lokálny Node je 26, CI je pripravené na Node 22.
- Prešiel `composer validate --strict --no-check-publish`, PHP syntax zmenených súborov, parse workflow YAML a `git diff --check`.
- Negatívny beh s názvom DB bez segmentu `test` skončil na ochrane prostredia ešte pred testovacím setupom.
- Workflow ešte nebol spustený na GitHube; beh na čistom MySQL 8.4/Node 22 potvrdí až CI. Sieťové generovanie sitemap a prehliadačový end-to-end test neboli spustené.
- Vývojová a produkčná DB sa nemenili. Zmeny nie sú nasadené. Pred aktiváciou prevádzkových kontrol nasadiť migráciu, obnoviť konfiguráciu a worker, zapnúť monitor a pripojiť nezávislé doručovanie alarmov podľa `docs/OPERATIONS.md`.
