# Objednávka z e-mailu

V košíku je pre rolu `super-admin` pri vyhľadávaní podľa IČO odkaz **Doplniť z e-mailu pomocou AI**. Dostupný je aj v prázdnom košíku. Endpoint `POST /api/email-order-preview` overuje túto rolu aj na serveri a povoľuje najviac 5 požiadaviek za minútu.

Import používa existujúci `OPENAI_API_KEY`. Voliteľný `OPENAI_ORDER_MODEL` nastavuje model Responses API (predvolene `gpt-6-luna`); musí podporovať structured outputs a web search. Server/proxy musí dovoliť požiadavku trvajúcu do 155 sekúnd. Text e-mailu sa posiela do OpenAI s `store: false`, aplikácia ho neukladá ani neloguje.

AI rozpozná údaje a vyberie varianty z publikovaného katalógu. Server vyhľadá existujúceho zákazníka podľa IČO alebo e-mailu zákazníka/kontaktu. Konflikty identity ostanú na ručné overenie. Chýbajúce údaje doplní register a webové dohľadávanie, ktorého zdroje sa zobrazia v náhľade. Neznáme údaje sa nevymýšľajú. Ceny a minimálny odber určuje databáza, nie AI.

Po kontrole návrhu sa nahradia fakturačné údaje v košíku a pridajú vybrané položky. Nejednoznačné položky treba zvoliť ručne. Odlišná dodacia adresa, doprava a platba sa zachovajú v poznámke a upozorneniach na ručné nastavenie. Import nevytvára objednávku ani zákazníka a neposiela e-maily; to sa udeje cez bežné odoslanie košíka.

## Prijaté e-maily (automaticky cez Account)

Nový mail na `obchod@` mimo vlákna objednávky ostane v Inboxe Accountu nepriradený a zastavy dostanú webhook `message.unassigned`. `EmailOrderInbox` ho uloží ako návrh (`email_order_drafts`) aj s prílohami a job `ProcessEmailOrder` ho pošle AI. Tá mail zaradí (`kind`): objednávka, dopyt na cenovú ponuku, alebo iné → stav `not_order` bez dohľadávania na webe. Objednávka aj dopyt prejdú tým istým `EmailOrderImport::preview` (zákazník, register, varianty).

**Dopyt na cenovú ponuku** vznikne vždy hneď, rovnako ako dopyt z webu: cenová ponuka v stave „dopyt“ so zdrojom `email` (filter „Dopyt z e-mailu“ v Cenových ponukách). Spárované položky sú v `requested_items`, poznámka AI a nespárované položky v zadaní (`brief`), upozornenia v internej poznámke, prílohy pri ponuke a pôvodný mail ako neprečítaná správa zákazníka v konverzácii ponuky. Maily k ponukám zatiaľ idú lokálne (nie cez Account), takže konverzácia v Inboxe Accountu ostane nepriradená.

**Automatické vytvorenie** (`EmailOrderAutoCreate`) – len keď platí všetko:

- `EMAIL_ORDERS_AUTO_CREATE=true` a nastavená aktívna doprava `EMAIL_ORDERS_SHIPPING_METHOD_ID` (platba `EMAIL_ORDERS_PAYMENT_METHOD_ID` voliteľne),
- zákazník je jednoznačný; pri existujúcom musí byť odosielateľ jeho kontaktom (e-mail firmy alebo kontaktnej osoby), nový musí mať IČO,
- vyplnené povinné údaje košíka (firma, meno, e-mail, telefón, ulica, PSČ, mesto),
- všetky položky spárované s množstvom aspoň minimálny odber a AI nemá žiadne pochybnosti (`warnings`).

Objednávka vznikne bežnou cestou e-shopu (ceny z DB, číslo, potvrdenie). Existujúca firma sa nemení. Inak ostane návrh a dôvody sa uložia (`reasons`) – aj pri vypnutej automatike, takže je vidieť, ako často by obstála.

**Obsluha** (super-admin): Objednávky → **Prijaté e-maily**. Detail ukáže pôvodný mail, prílohy, návrh a dôvody. **Otvoriť v košíku** naplní košík uloženým návrhom (AI sa znova nevolá), po odoslaní sa objednávka prepojí s mailom. Ďalej **Spracovať ako cenovú ponuku** (keď AI dopyt zaradila ako objednávku – vznikne ponuka v stave „dopyt“ rovnako ako pri automatickom dopyte), **Nie je objednávka** a **Spracovať znova**.

**Pri vytvorení objednávky** (automaticky aj z košíka) sa pôvodný mail stane prvou správou zákazníka v konverzácii objednávky aj s prílohami (napr. sken ručne písanej objednávky). Do poznámky ide len riadok „Objednávka z e-mailu od …“. Potvrdenie odíde s `conversation` = konverzácia mailu v Accounte – Account si ju tým priradí k objednávke, potvrdenie príde zákazníkovi ako odpoveď na jeho mail a jeho ďalšie odpovede už idú k objednávke.

Zapnutie: `EMAIL_ORDERS=true` (+ `ACCOUNT_MESSAGING`, `OPENAI_API_KEY`), vo webhooku v Accounte pridať udalosť `message.unassigned`, `php artisan migrate`. Text mailu z obrázka (sken) AI nečíta – takú objednávku doplní obsluha z prílohy.

Overenie: `php artisan test --filter=EmailOrderImportTest` a `--filter=EmailOrderInboxTest` v `api`, `npm run typecheck` a `npm run build:ci` v `ui`. Testy používajú oddelenú testovaciu databázu a falošné odpovede AI/registra.

Použité API: https://developers.openai.com/api/docs/guides/tools-web-search a https://developers.openai.com/api/docs/guides/structured-outputs.
