# Objednávka z e-mailu

V košíku je pre rolu `super-admin` pri vyhľadávaní podľa IČO odkaz **Doplniť z e-mailu pomocou AI**. Dostupný je aj v prázdnom košíku. Endpoint `POST /api/email-order-preview` overuje túto rolu aj na serveri a povoľuje najviac 5 požiadaviek za minútu.

Import používa existujúci `OPENAI_API_KEY`. Voliteľný `OPENAI_ORDER_MODEL` nastavuje model Responses API (predvolene `gpt-6-luna`); musí podporovať structured outputs a web search. Server/proxy musí dovoliť požiadavku trvajúcu do 155 sekúnd. Text e-mailu sa posiela do OpenAI s `store: false`, aplikácia ho neukladá ani neloguje.

AI rozpozná údaje a vyberie varianty z publikovaného katalógu. Server vyhľadá existujúceho zákazníka podľa IČO alebo e-mailu zákazníka/kontaktu. Konflikty identity ostanú na ručné overenie. Chýbajúce údaje doplní register a webové dohľadávanie, ktorého zdroje sa zobrazia v náhľade. Neznáme údaje sa nevymýšľajú. Ceny a minimálny odber určuje databáza, nie AI.

Po kontrole návrhu sa nahradia fakturačné údaje v košíku a pridajú vybrané položky. Nejednoznačné položky treba zvoliť ručne. Odlišná dodacia adresa, doprava a platba sa zachovajú v poznámke a upozorneniach na ručné nastavenie. Import nevytvára objednávku ani zákazníka a neposiela e-maily; to sa udeje cez bežné odoslanie košíka.

Overenie: `php artisan test --filter=EmailOrderImportTest` v `api`, `npm run typecheck` a `npm run build:ci` v `ui`. Testy používajú oddelenú testovaciu databázu a falošné odpovede AI/registra.

Použité API: https://developers.openai.com/api/docs/guides/tools-web-search a https://developers.openai.com/api/docs/guides/structured-outputs.
