# Nasadenie a prevádzka

Tento postup je pripravený pre server s PHP, MySQL, statickým frontend hostingom a prístupom ku cron/správcovi procesov. Konfigurácia servera a externého doručovania alarmov nie je automaticky nasadená. Príklady ciest a používateľov nahraďte konkrétnymi hodnotami hostingu.

## 1. Nasadenie

1. Overte zelené CI pre konkrétny commit. Uchovajte identifikátor predchádzajúceho release.
2. Zálohujte databázu, používateľské súbory a bezpečne aj konfiguráciu vrátane `APP_KEY`. Overte čitateľnosť zálohy a dostupnosť úložiska. Pri databázových zmenách zastavte zapisovanie aplikácie a nechajte workery dokončiť rozpracované úlohy, potom ich zastavte.
3. Pripravte nový release mimo bežiaceho adresára. V `api` spustite `composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction`. Preneste konfiguráciu bezpečným mechanizmom hostingu; `.env` nesmie byť verejne dostupný. `APP_ENV=production`, `APP_DEBUG=false`, správne `APP_URL` a `FRONTEND_URL`.
4. Backendový document root musí smerovať na `api/public`. Práva na zápis potrebuje `storage` a `bootstrap/cache`; neotvárajte zápis do celého projektu.
5. Po kontrole pripojenia vykonajte `php artisan migrate --force`. Nová migrácia bodu 4 pridáva `failed_jobs`; neprepisuje objednávky. Pri prvom nasadení bodov 2 a 3 postupujte aj podľa sekcií ich migrácií v analýze: odtlačky objednávok a kontrola historických vratiek.
6. Vykonajte `php artisan config:cache` a `php artisan view:cache`. Pred opätovným buildom cache po zmene konfigurácie používajte `php artisan config:clear`. Pri lokálnom disku vytvorte `php artisan storage:link`.
7. V `ui` nastavte produkčné URL, spustite `npm ci`, `npm run typecheck`, `npm test`, `npm run build`. Overte log generovania sitemap a publikujte obsah `ui/dist` s SPA fallbackom na `index.html`.
8. Prepnite release, spustite workery a obnovte aplikáciu. Pri dlhobežiacich workeroch vykonajte `php artisan queue:restart`; správca procesov musí worker znovu spustiť. Overte `/up`, verejný katalóg, prihlásenie a čítanie objednávky. Skúšobné objednávky a e-maily robte na stagingu s testovacími príjemcami.
9. Po prvom päťminútovom cykle monitora overte `php artisan ops:check --json`. Prvý beh pred prvým spracovaným heartbeat jobom môže hlásiť chýbajúci worker.

## 2. Fronta

Pre produkciu použite `QUEUE_CONNECTION=database` (alebo pripravené Redis/SQS prostredie), trvalú cache a doručujúci mailer. `sync` nie je náhrada samostatného workera. Ukážka Supervisor konfigurácie:

```ini
[program:zastavy-queue]
command=/usr/bin/php /srv/zastavy/current/api/artisan queue:work database --queue=default --sleep=3 --tries=3 --timeout=60 --max-time=3600
directory=/srv/zastavy/current/api
user=www-data
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
stopwaitsecs=3600
redirect_stderr=true
stdout_logfile=/var/log/zastavy-queue.log
```

`--timeout=60` je kratší ako predvolený `DB_QUEUE_RETRY_AFTER=90`. Pri zmene timeoutov zachovajte tento vzťah, inak môže byť tá istá úloha spracovaná súbežne. Rotáciu logu nastavte na serveri. Používaný queue názov musí zodpovedať `DB_QUEUE`; ak ho zmeníte, upravte aj `--queue` workera.

Zlyhané úlohy:

```sh
php artisan queue:failed
php artisan queue:retry UUID_KONKRETNEJ_ULOHY
```

Najprv opravte príčinu a skontrolujte možný už vykonaný vedľajší účinok (napríklad odoslaný e-mail). Neopakujte automaticky všetky úlohy. Payload aj exception v `failed_jobs` môžu obsahovať osobné údaje; obmedzte prístup a nastavte retenčnú politiku. `queue:prune-failed --hours=168` použite až po vyriešení incidentov a schválení retencie.

## 3. Scheduler

Na serveri spúšťajte každú minútu (POSIX cron):

```cron
* * * * * cd /srv/zastavy/current/api && /usr/bin/php artisan schedule:run >> /var/log/zastavy-scheduler.log 2>&1
```

`php artisan schedule:list` vypíše naplánované úlohy. Existujúca kontrola zákazníkov beží každých päť minút s časovým rozpočtom 25 sekúnd. V testovacom/staging prostredí nastavte `CUSTOMER_REVIEW_ENABLED=false`, ak sa nemajú volať externé registre a AI.

## 4. Kontroly a alarmy

V produkčnom `.env` nastavte:

```dotenv
OPS_MONITOR_ENABLED=true
OPS_QUEUE_MAX_SIZE=100
OPS_HEARTBEAT_MAX_AGE=900
OPS_FAILED_JOB_WINDOW_HOURS=24
CACHE_STORE=database
QUEUE_CONNECTION=database
```

Po zmene obnovte config cache a workery. Scheduler každých päť minút zapíše svoj čas a odošle malú úlohu do predvolenej fronty; worker pri jej spracovaní zapíše vlastný čas. Oba procesy musia používať rovnakú trvalú cache, DB a cache prefix. Monitor kontroluje predvolené pripojenie a frontu; ďalšie fronty vyžadujú vlastné monitorovanie.

`php artisan ops:check --json` overuje:

| Kontrola | Podmienka zdravého stavu |
|---|---|
| Databáza | `SELECT 1` prejde |
| Cache | zápis, čítanie a odstránenie dočasného kľúča prejdú |
| Scheduler | heartbeat nie je starší ako 900 sekúnd |
| Worker | heartbeat asynchrónnej fronty nie je starší ako 900 sekúnd |
| Backlog | veľkosť predvolenej fronty je najviac 100 úloh |
| Zlyhané úlohy | bez zlyhania v posledných 24 hodinách, dostupné databázové úložisko zlyhaní |

Prahy sú konfigurovateľné. Príkaz vráti exit kód 1 pri probléme a zapíše udalosť `operations.unhealthy` do aplikačného logu; výstup neobsahuje payload úloh ani zákaznícke údaje. Scheduled kontrola funguje len počas behu schedulera. **Nezávislý monitor musí pravidelne spúšťať tento príkaz, zachytiť nenulový exit kód aj nedostupnosť celého servera a doručiť alarm obsluhe.** Bez konfigurácie tohto externého monitora ide o lokálnu detekciu/logovanie, nie o doručené upozornenie.

Monitorujte aj HTTPS `/up` zvonku. Táto Laravel cesta overuje štart aplikácie; sama nepotvrdzuje fungovanie DB, skladu, SMTP ani S3. S3/SMTP dostupnosť, kapacita disku, vek úspešnej zálohy a úspešnosť obnovy vyžadujú kontroly na úrovni hostingu/služieb. API odpovede 5xx, vysoká latencia a opakované 422 pri expedícii sú užitočné doplnkové signály.

### Reakcia na problém

- `database` / `cache`: dostupnosť služby, prihlasovanie, voľný disk; nereštartujte naslepo všetky workery.
- `scheduler`: cron, práva, správna release cesta, log schedulera a trvalá cache.
- `queue_worker`: Supervisor/systemd stav, posledný worker log, fronta/pripojenie, timeouty, aktuálna konfigurácia.
- `queue_backlog`: počet a vek úloh, rýchlosť spracovania, obmedzenia SMTP; škálujte až po identifikovaní príčiny.
- `failed_jobs`: nájdite konkrétnu úlohu cez `queue:failed`, opravte príčinu, potom cielene opakujte.

## 5. Zálohy a obnova

Frekvenciu záloh nastavte podľa prípustnej straty objednávok. Minimálny prevádzkový základ: automatická denná záloha a záloha pred každou migráciou; pri menšej tolerancii straty použite častejšie zálohy alebo obnovu do bodu v čase. Zálohujte DB aj používateľské súbory (vrátane súkromných príloh), zapnite verziovanie objektového úložiska a uchovávajte oddelenú kópiu mimo aplikačného servera. Tajomstvá a `APP_KEY` uložte oddelene s obmedzeným prístupom.

Použite spravované zálohy hostingu alebo kompatibilný `mysqldump --single-transaction` pre InnoDB. Heslo dodávajte cez chránený konfiguračný súbor/nástroj hostingu, nie argumentom zanechaným v histórii shellu. Nekopírujte živý databázový adresár. Existujúci SQL export v Gite nie je prevádzková záloha.

Pravidelne obnovte zálohu do izolovanej databázy a izolovaného adresára/bucket prefixu. V obnovenom prostredí vypnite doručovanie pošty a externé integrácie. Skontrolujte počty objednávok, položiek, pohybov skladu a sprístupnenie príloh. Zaznamenajte dátum obnovy a poslednú dostupnú objednávku; samotná existencia archívu nestačí.

## 6. Rollback

Pred návratom starej verzie zastavte nové zápisy a workery. Ak schéma zostáva spätne kompatibilná, vráťte predchádzajúci backend/frontend a obnovte config cache a worker procesy. `migrate:rollback` nespúšťajte automaticky: migrácie bodov 2 a 3 odstraňujú historické odtlačky, kľúče opakovaných odoslaní a význam vratiek. Migrácia bodu 4 pri rollbacku zahodí evidenciu zlyhaných úloh. Pri potrebe obnovy DB počítajte s objednávkami vzniknutými po zálohe a s už odoslanými oznámeniami.

## Referencie

- [Vue: typová kontrola](https://vuejs.org/guide/typescript/overview)
- [GitHub Actions: service kontajnery](https://docs.github.com/en/actions/tutorials/use-containerized-services)
- Lokálne implementácie a konfigurácie v `api/config/queue.php`, `api/routes/console.php`, `api/app/Services/OperationsHealth.php` sú zdrojom konkrétnych hodnôt uvedených vyššie.
