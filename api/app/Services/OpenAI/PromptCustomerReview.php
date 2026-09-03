<?php

namespace App\Services\OpenAI;

/**
 * Posúdenie údajov zákazníka — tretia vrstva, tá drahá.
 *
 * Dostáva už len to, na čo pravidlo napísať nevieme: či je „SošPg Modra"
 * názov firmy alebo skratka napísaná narýchlo, či je „JOzef" preklep, či
 * v poli „Názov firmy" nesedí meno človeka a naopak. Formát IČO, DIČ,
 * telefónu ani PSČ sa modelu neposiela ako úloha — to už vyriešil
 * CustomerDataRules zadarmo a presne.
 *
 * Dve tvrdé hranice, obe zámerné:
 *
 *   1. Modelu sa NEPOSIELA e-mail ani telefón. Sú to osobné údaje a na
 *      posúdenie veľkých písmen v názve obce nie sú potrebné.
 *   2. Model smie mať výhradu len k poliam z FIELDS — teda k textu. Daňové
 *      čísla mu nepatria: tie má na starosti kontrolná číslica a register,
 *      ktoré nehádajú.
 *
 * Model NEOPRAVUJE — navrhuje. Návrh potvrdí človek v detaile zákazníka
 * jedným klikom. Tiché prepísanie fakturačného údaja podľa odhadu jazykového
 * modelu je presne to, čo si na faktúre nikto nevšimne včas.
 */
class PromptCustomerReview
{
    /** Polia, ku ktorým smie mať model výhradu. */
    public const FIELDS = ['name', 'company', 'street', 'city'];

    /** Závažnosti, ktoré smie model použiť. `error` si necháva pravidlo. */
    public const SEVERITIES = ['notice', 'warning'];

    public function jsonSchema(): array
    {
        return [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'customer_review_schema',
                'strict' => true,
                'schema' => [
                    'type' => 'object',
                    'required' => ['score', 'summary', 'issues'],
                    'properties' => [
                        'score' => ['type' => 'integer'],
                        'summary' => ['type' => 'string'],
                        'issues' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'required' => ['field', 'severity', 'message', 'suggested'],
                                'properties' => [
                                    'field' => ['type' => 'string', 'enum' => self::FIELDS],
                                    'severity' => ['type' => 'string', 'enum' => self::SEVERITIES],
                                    'message' => ['type' => 'string'],
                                    // Hodnota, ktorá má byť v poli namiesto
                                    // súčasnej. null, keď model vie povedať, že
                                    // je niečo zle, ale nie čím to nahradiť —
                                    // taký nález ide človeku, nie do tlačidla.
                                    'suggested' => ['type' => ['string', 'null']],
                                ],
                                'additionalProperties' => false,
                            ],
                        ],
                    ],
                    'additionalProperties' => false,
                ],
            ],
        ];
    }

    /**
     * @param  array<string, string|null>  $customer
     * @param  array<string, string|null>|null  $registry
     * @return array<int, array{role: string, content: string}>
     */
    public function prompt(array $customer, ?array $registry = null): array
    {
        $lines = '';

        foreach (['company' => 'Názov firmy', 'name' => 'Kontaktná osoba', 'street' => 'Ulica a číslo', 'city' => 'Mesto/obec'] as $key => $label) {
            $lines .= $label.': '.$this->show($customer[$key] ?? null)."\n";
        }

        // IČO ide do promptu len ako kontext („je to organizácia"), nie na
        // posúdenie — jeho správnosť už overila kontrolná číslica.
        $lines .= 'IČO (len ako informácia, neposudzuj ho): '.$this->show($customer['ico'] ?? null)."\n";

        $registryBlock = '';

        if ($registry !== null) {
            $registryBlock = "\nÚdaje toho istého IČO z obchodného registra (autoritatívne):\n"
                .'Názov firmy: '.$this->show($registry['company'] ?? null)."\n"
                .'Ulica a číslo: '.$this->show($registry['street'] ?? null)."\n"
                .'Mesto/obec: '.$this->show($registry['city'] ?? null)."\n";
        }

        return [
            [
                'role' => 'system',
                'content' => 'Si korektor fakturačných údajov slovenského e-shopu so zástavami a vlajkami.
Zákazníkmi sú najmä obce, mestské časti, školy, farnosti a firmy. Údaje si do objednávkového formulára píše sám zákazník, takže sú neupravené.

Dostaneš jeden riadok z databázy zákazníkov. Tvojou úlohou je POSÚDIŤ ho, NIE prepísať.

ČO HĽADÁŠ (v tomto poradí dôležitosti):
1. Pole má zlý obsah — v „Názov firmy" je meno človeka, adresa alebo číslo; v „Kontaktná osoba" je názov úradu alebo slovo ako „sekretariat", „uctaren" → severity "warning".
2. Zjavný preklep alebo rozbité veľké písmená vo vnútri slova: „JOzef", „sektretariat", „ZAKLADNA SKOLA", „obec pruske" → severity "warning".
3. Chýbajúca diakritika v slovenskom názve, ktorý poznáš s istotou („Kosice" → „Košice", „Zilina" → „Žilina") → severity "warning".
4. Prvé písmeno názvu firmy alebo obce je malé („obec Pruské" → „Obec Pruské") → severity "notice".
5. V „Ulica a číslo" je aj mesto alebo PSČ, ktoré patria do vlastných polí → severity "notice".

PRAVIDLÁ:
- NEVYMÝŠĽAJ chyby. Keď je riadok v poriadku, vráť prázdne pole issues a score 100.
- NEOPRAVUJ vlastné mená, názvy obcí, škôl, farností ani priezviská, ktoré nepoznáš. Keď si nie si istý, mlč. Radšej prehliadnutá chyba než návrh, ktorý pokazí správny údaj.
- Skratky a úradné názvy („ZŠ s MŠ", „SOŠPg", „Mestská časť Bratislava-Čunovo", „ROEP") sú SPRÁVNE. Nerozpisuj ich a nehlás ako chybu.
- Číslo domu bez názvu ulice („Bidovce 210", „č. 16") je v malých obciach správne. Nehlás to.
- Neposudzuj IČO, DIČ, IČ DPH, PSČ, telefón ani e-mail. Tie kontroluje iná vrstva a tvoje návrhy k nim sa zahodia.
- Keď dostaneš aj údaje z registra a názov firmy sa od nich líši viac než veľkým písmenom či skratkou, navrhni presné znenie z registra.
- Maximálne 5 výhrad. Keď je ich viac, vyber najzávažnejšie.
- message píš po slovensky, jednou vetou, ako radu človeku („V názve firmy je meno kontaktnej osoby.").
- suggested je celá nová hodnota poľa, nie popis zmeny. Keď nevieš, čím nahradiť, daj null.
- score je 0-100: 100 = bez výhrad, pod 60 = riadok potrebuje ľudský zásah.
- summary je jedna veta o stave riadku.
- Vráť iba validný JSON bez ďalšieho textu.',
            ],
            [
                'role' => 'user',
                'content' => "Posúď tieto údaje zákazníka:\n".$lines.$registryBlock,
            ],
        ];
    }

    public function validator(): array
    {
        return [
            'score' => 'required|integer|min:0|max:100',
            'summary' => 'required|string',
            'issues' => 'present|array',
            'issues.*.field' => 'required|string|in:'.implode(',', self::FIELDS),
            'issues.*.severity' => 'required|string|in:'.implode(',', self::SEVERITIES),
            'issues.*.message' => 'required|string',
            'issues.*.suggested' => 'present|nullable|string',
        ];
    }

    private function show(?string $value): string
    {
        $value = trim((string) $value);

        return $value === '' ? '(prázdne)' : $value;
    }
}
