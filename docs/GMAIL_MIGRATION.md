# Historické objednávky z Gmailu

`orders:import-gmail` vkladá skontrolovaný súkromný JSON manifest do databázy nakonfigurovanej v aplikácii. Import je iba prírastkový; existujúce zákaznícke údaje ani objednávky nemení. E-shopové správy, opakované zaslania, dopyty a už evidované objednávky treba odstrániť pri príprave manifestu.

```sh
php artisan orders:import-gmail /private/reviewed-manifest.json --receipt=/private/preview.json
php artisan orders:import-gmail /private/reviewed-manifest.json --apply --receipt=/private/import-receipt.json
```

Bez `--apply` prebehne kontrola a spočítanie bez zápisu. Pred zápisom vytvorte zálohu databázy. Celý import prebieha v transakcii. UUID a číslo objednávky sú odvodené zo zdrojového účtu a stabilného identifikátora záznamu, preto opakované spustenie rovnaké objednávky preskočí. Potvrdenka obsahuje ID vytvorených záznamov a SHA-256 manifestu. Ak zlyhá uloženie potvrdenky po dokončení transakcie, import už môže byť zapísaný; druhý kontrolný beh ukáže preskočené záznamy.

Manifest má `account` a pole `records`. Záznam obsahuje `source_id`, `source_ids`, `source_date` s časovým pásmom, `source_subject`, `source_body`, `status: archived`, `customer_id` alebo `null`, `company`, `name`, `email`, `phone`, `street`, `city`, `postcode`, `ico`, `dic`, `missing` a `items`. Každá položka má `name`, `quantity` a `price` (aj `null`). Pri rozdelení jedného e-mailu medzi dve organizácie použite pre každú časť vlastný stabilný identifikátor `source_id`; `source_ids` stále obsahuje skutočné Gmail ID.

Známe ceny majú `price_origin: email`. Dopĺňané historické ceny majú `price_origin: historical_order` a `price_reference` s `order_id`, `order_product_id`, `product_id`, `date` a `serial`. Import overí cenu a väzby referenčnej položky v cieľovej databáze. Vyberajte najbližšiu predchádzajúcu cenu rovnakého produktu; neskorší podklad pre staršie e-maily použite iba podľa dohodnutých pravidiel. Chýbajúce množstvo možno doplniť na 1 ks podľa pokynu vlastníka. Nezamieňajte chýbajúci údaj s výslovne uvedeným iným množstvom. Ak druh tovaru alebo referenčná cena nie sú identifikovateľné, ponechajte chýbajúce údaje označené.

Objednávky aj položky dostanú pôvodný dátum v časovom pásme aplikácie. Historické položky sú vlastné položky s odtlačkom názvu, bez väzby na aktuálny katalóg a bez domyslenej DPH. Do poznámky sa uloží zdrojový text, odkazy do Gmailu, doplnené údaje a chýbajúce podklady. Prílohy sa automaticky nekopírujú.

Zákazníci sa párujú podľa IČO a e-mailov kontaktov. Zadané existujúce ID musí súhlasiť s identitou, aby sa manifest nedal omylom priradiť inej databáze. Nové kontakty majú deaktivované prihlásenie, náhodné heslo, žiadnu rolu a neoverený e-mail. Používajú sa priame databázové zápisy bez modelových udalostí, e-mailov, kupónov, expedície a skladových pohybov.

Manifesty, zdrojové e-maily, zálohy a reporty obsahujú osobné údaje. Uchovávajte ich mimo verejného webového koreňa a mimo Git repozitára; lokálne pracovné súbory patria do ignorovaného `.tmp/gmail-migration/`.