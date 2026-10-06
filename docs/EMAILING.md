# Emailing

Sekcia **Emailing** je dostupná administrátorom na `/admin/emailing`.

## Prvý postup

1. V Príjemcoch vložte oprávnené emailové adresy alebo importujte zákaznícke kontakty. Uveďte zdroj a dôvod oprávnenia na zasielanie. Existujúce kontakty sa automaticky neprihlasujú; import nikdy nezruší odhlásenie. Adresy sa normalizujú na malé písmená a deduplikujú.
2. Vyberte jednu z piatich šablón alebo vytvorte vlastnú. Napíšte predmet, nadpis, text a odkaz tlačidla. Kupón vyberte z existujúcej správy kupónov. Uložte koncept a obnovte náhľad po úpravách.
3. Po nastavení odosielania pošlite test sebe. Test ide len na email prihláseného administrátora.
4. Zvoľte čas alebo nechajte okamžitý začiatok. Potvrdením sa obsah uzamkne a vytvorí zoznam aktuálne aktívnych príjemcov. Nové kontakty sa spätne nepridávajú.
5. V kampaniach sledujte záznamy; čakajúcu kampaň možno zastaviť. Práve prebiehajúci SMTP pokus sa môže dokončiť. Odhlásenie sa kontroluje tesne pred odoslaním.

Šablóna **Spisor.eu – Anonymizer** zachováva vzhľad a informačné bloky dodaného HTML. Predmet, nadpis, hlavný text, tlačidlo a kupón sú upraviteľné. Značky AcyMailingu boli nahradené adresou príjemcu a vlastným odhlasovacím odkazom; jeho reklamný obrázok sa nepoužíva. Vybraný vzhľad sa zachová aj pri uložení vlastnej šablóny a kopírovaní kampane.

Šablóna **Vlajky a štátne symboly** obsahuje štyri produktové karty z dodaného HTML (obrázky, popisy, ceny a odkazy), obecné zástavy a kontakty. Predmet, nadpis, úvodný text, hlavné tlačidlo a kupón sa upravujú v editore. Produktové údaje a ceny sú súčasťou šablóny, nesynchronizujú sa automaticky s katalógom. Obrázky sa načítavajú z produktových záznamov cez trvalý verejný endpoint `/api/images/{id}`; nepoužívajú pevné cesty ani dočasné podpísané adresy. V zozname šablón je skutočný náhľad emailu. Na mobile sa produktové karty zobrazujú pod sebou. Používa vlastné odhlásenie systému bez pätičky AcyMailingu.

## Nasadenie

### Automatizácia po objednávke

V editore kampane vyberte **Spôsob spustenia → Po vytvorení objednávky**, nastavte oneskorenie v hodinách (predvolene 24), pripravte obsah a potvrďte aktiváciu. Existujúce objednávky sa spätne nespracujú. Každá nová objednávka, vrátane ručne vytvorenej alebo importovanej cez model objednávky, naplánuje jednu ponuku pre zodpovedajúci aktívny kontakt v adresári. Objednávka sama nepridáva kontakt ani neobnovuje odhlásenie. Viac objednávok jedného kontaktu znamená viac ponúk; viac aktívnych automatizácií sa spúšťa nezávisle.

Čas sa počíta od vytvorenia objednávky, nie od doručenia potvrdenia. Správa sa odošle najskôr po uplynutí oneskorenia pri najbližšom behu plánovača v rámci spoločného limitu. Záznam uvádza číslo objednávky a plánovaný čas. Automatizácia zostáva aktívna aj po vyprázdnení fronty. Zastavenie zruší čakajúce ponuky aj prijímanie nových udalostí; pre zmenu obsahu alebo opätovné spustenie vytvorte kópiu.

Pred odoslaním sa kontroluje odhlásenie, existencia a storno objednávky, zhoda emailu a platnosť pripojeného kupóna. Nevyhovujúce pokusy sa označia ako preskočené. Prerušené ani zlyhané SMTP pokusy sa automaticky neopakujú. Identifikátor objednávky zabraňuje duplicitnému naplánovaniu tej istej udalosti.

Transakčné notifikácie ostávajú vo svojej existujúcej ceste. Spoločný `SystemLogSubscriber` už zachytáva odoslané transakčné aj marketingové emaily. Odporúčaný ďalší krok je spoločný prehľad s filtrami podľa typu, príjemcu a objednávky; potvrdenia nemajú závisieť od marketingového odhlásenia, vypnutia emailingu alebo jeho hodinového limitu.

Nová migrácia vytvorí štyri tabuľky, existujúcich zákazníkov nemení:

```sh
php artisan migrate --path=database/migrations/2026_10_05_160000_create_emailing_tables.php --force
php artisan migrate --path=database/migrations/2026_10_05_170000_add_emailing_layout.php --force
php artisan migrate --path=database/migrations/2026_10_05_180000_add_emailing_triggers.php --force
php artisan migrate --path=database/migrations/2026_10_05_190000_add_emailing_measurement.php --force
```

Nastavenie prostredia (heslá zostávajú len v konfigurácii servera):

```dotenv
EMAILING_ENABLED=false
EMAILING_HOURLY_LIMIT=100
EMAILING_BATCH_SIZE=10
MAIL_MAILER=smtp
```

Doplňte MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD, MAIL_FROM_ADDRESS a MAIL_FROM_NAME podľa poštového poskytovateľa. Nastavte verejnú HTTPS APP_URL – generujú sa z nej odkazy na odhlásenie. Overte odosielateľskú doménu u poskytovateľa, jej SPF/DKIM a doručenie testu do vlastnej schránky. Po príprave nastavte EMAILING_ENABLED=true a obnovte konfiguračnú cache (`php artisan config:cache`). Log, array a failover mailery sa nepovažujú za doručujúce.

Plánovač musí každú minútu spúšťať `php artisan schedule:run`. Príkaz `emailing:send` spracuje najviac EMAILING_BATCH_SIZE príjemcov. Nepotrebuje queue worker. Hodinový limit je spoločný pre všetky kampane a počíta pokusy za posledných 60 minút; testovacie správy majú samostatný HTTP throttle. Scheduler nepúšťajte s neobmedzeným SMTP timeoutom: nakonfigurujte primeraný timeout poskytovateľa/servera. Viac plánovačov koordinuje databázový zámok; produkcia vyžaduje MySQL/InnoDB.

## Význam záznamov

### Meranie odozvy

V editore sú samostatné prepínače pre **návštevy odkazov** a **odhad otvorenia obrázkom**. Nové kampane majú v rozhraní predvolené meranie kliknutí; otvorenia sú vypnuté. Existujúce kampane si ponechávajú vypnuté meranie, už odoslané emaily nemožno zmerať spätne. Nastavenie sa uzamkne spolu s obsahom pri aktivácii. Náhľad a test sebe nemajú meracie odkazy ani pixel.

Meranie používa vlastnú HTTPS adresu API z APP_URL. Odkazy obsahujú náhodné 64-znakové tokeny, nie email ani ID príjemcu. Cieľ presmerovania je pevne uložený pri odoslaní; klient ho nemôže zmeniť parametrom. Nezapájame externú analytickú službu, JavaScript, fingerprinting ani analytické cookies. Analytické tabuľky ukladajú iba prvý čas a počty, nie IP adresy ani user-agenty. Bežné prístupové logy webservera majú vlastné nastavenie a retenčnú dobu.

Merajú sa HTTPS tlačidlá a produktové obrázky/odkazy vo všetkých troch vzhľadoch. Odhlásenie, mailto, tel, HTTP a odkazy, ktorých text zobrazuje webovú adresu, sa nemenia. Posledné z nich zostávajú priame, aby viditeľná adresa zodpovedala cieľu. Email obsahuje stručnú informáciu o zapnutom meraní. Pri meraní otvorení navyše obsahuje štandardný obrázok 1 × 1 px.

V záznamoch kampane vidno prvú návštevu/načítanie obrázka pri správe a súhrn podľa cieľovej adresy. Obrázok a tlačidlo jedného produktu sa agregujú; počet „správ s návštevou“ ich v rámci jednej správy deduplikuje. Súhrn v Štatistikách sleduje odozvu na správy odoslané vo vybranom dátumovom období, aj keď návšteva nastala neskôr. Nejde o počet jedinečných ľudí: preposlaný email má rovnaký token. Nemerané správy nemajú údaje; chýbajúci údaj neznamená, že ich človek nečítal.

HEAD, deklarované prefetch požiadavky a známe roboty/skenerové user-agenty nezvyšujú počty. Filtrovanie je len orientačné: bezpečnostné skenery sa môžu správať ako prehliadač. Apple Mail Privacy Protection môže obrázok načítať bez otvorenia, Gmail používa proxy/cache a klienti môžu obrázky blokovať. Preto neuvádzame „prečítané“ ani garantované ľudské kliknutia. Neobchádzame ochrany klientov a negarantujeme doručiteľnosť: závisí aj od domény, obsahu a reputácie. SPF/DKIM/DMARC a správne verejné HTTPS zostávajú dôležité.

Nové meranie sa zastaví po odhlásení kontaktu alebo po 90 dňoch od pokusu o odoslanie; doterajšie agregáty zostávajú. Odkazy ďalej presmerúvajú na uložený cieľ. Výnimka pri zápise štatistík nezastaví presmerovanie; výpadok databázy pri čítaní cieľa ho však ovplyvní, pretože cieľ je uložený na serveri.

Podklady: [Apple Mail Privacy Protection](https://support.apple.com/en-ca/guide/iphone/iphf084865c7/ios), [obrázky v Gmaile](https://support.google.com/mail/answer/145919), [Microsoft Safe Links](https://learn.microsoft.com/en-us/defender-office-365/safe-links-about).

- Čaká: zatiaľ nespracované.
- Odosielanie / overiť: rezervovaný pokus. Ak stav zostane dlhšie, proces sa mohol prerušiť; overte poskytovateľa pošty.
- Odoslané: transport prevzal správu; nepotvrdzuje doručenie, prečítanie ani kliknutie.
- Zlyhalo: výnimka transportu; technické detaily sú v serverovom logu.
- Preskočené: odhlásený kontakt alebo zastavená kampaň.

Zlyhané ani prerušené pokusy sa automaticky neopakujú: SMTP neposkytuje záruku presne jedného doručenia pri strate spojenia. Pred opätovnou ponukou skontrolujte záznamy poskytovateľa. Kopírovanie kampane vytvorí nový koncept pre všetkých aktuálnych príjemcov, nie opakovanie iba zlyhaných.

Odkazy „Zrušiť odber“ hore v správe a v pätičke zobrazia potvrdzovaciu stránku (GET nemení stav, aby link skener neodhlasoval klientov). POST vykoná trvalé odhlásenie aj bez prihlásenia. Hlavičky List-Unsubscribe (s individuálnym HTTPS odkazom) a List-Unsubscribe-Post podporujú odhlásenie poštovým klientom jedným kliknutím bez ďalšieho potvrdenia. Objednávkové emaily zostávajú samostatné.

Gmail môže zobraziť vlastné „Zrušiť odber“ pri mene odosielateľa. O jeho zobrazení rozhodujú automatické kontroly Gmailu vrátane overenia domény a reputácie; samotné hlavičky nezaručujú zobrazenie. Pozri [pravidlá Gmailu](https://support.google.com/mail/answer/14229414?hl=en). Poštový poskytovateľ musí podpísať odhlasovacie hlavičky pomocou DKIM. Verejný HTTPS odkaz musí prijímať POST bez prihlásenia, CAPTCHA alebo presmerovania. Test sebe a náhľad zámerne neobsahujú aktívne odhlásenie ani tieto hlavičky; funkciu overujte na skutočnej kampani s vlastnou adresou v adresári príjemcov.

Počty podľa dátumu sú v časovom pásme aplikácie, zobrazenom v rozhraní. Plánovaný čas z editora sa prevádza z miestneho pásma prehliadača. Bez integrácie webhookov poskytovateľa sa neevidujú bounces. Návštevy odkazov a odhad otvorení sa merajú voliteľne vlastnými endpointmi (pozri Meranie odozvy). Zo zistených nedoručiteľných adries možno príjemcov ručne odhlásiť. Šablóny sú zámerne textové bloky s bezpečným HTML vykreslením, nie editor ľubovoľného HTML.

## Overenie

`EmailingTest` testuje oprávnenia, validáciu, HTML escaping, deduplikáciu, odhlásenie, nemennosť kampane, kontrolu odhlásenia pred odoslaním, plánovanie, limit, zrušenie a zlyhanie transportu. Používa simulovaný mailer a izolovanú testovaciu databázu. SQLite beh neoveruje súbehové MySQL zámky ani skutočnú doručiteľnosť.
